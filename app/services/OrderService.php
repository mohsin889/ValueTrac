<?php

namespace app\services;

use app\factories\ValueTracFactory;
use app\factories\LoggerFactory;
use app\models\response\Event;
use app\utility\ObjectCompare;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use app\utility;

class OrderService extends BaseService
{
    private $appShieldPdo;
    private $homebasePdo;
    private $valueTracService;
    private $log;

    public function __construct(Pdo $valueTracPdo, Pdo $homebasePdo)
    {
        parent::__construct();

        $this->appShieldPdo = $valueTracPdo;
        $this->homebasePdo = $homebasePdo;

        $this->valueTracService = new ValueTracService($valueTracPdo);
        $this->log = LoggerFactory::createLogger('OrderService');
    }

    public function createHomebaseGuzzleClient(): Client
    {
        $config = [
            'base_uri' => getenv('HB_URL'),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'auth' => [
                getenv('HB_AS_USERNAME'),
                getenv('HB_AS_PASSWORD')
            ],
            'timeout' => 30
        ];

        if (getenv('ENV') != 'Production') {
            $config['verify'] = false;
        }

        return new Client($config);
    }

    public function createOrder(Event $event, bool $is_child = false, object $new_order = null)
    {
        // ORDER.LOAN_FILE.APPRAISAL_TYPE MAPPING TO APPRAISAL.PURCHASE_TYPE
        $purchaseTypeValues = [
            'refinance' => 'Refinance',
            'purchase' => 'Purchase',
            'construction loan' => 'Construction',
            'construction to permanent' => 'Construction',
            'other' => 'Other',
            'n/a' => 'Other'
        ];

        // GATHER AppraisalShield ORDER FROM SERVICE
        if (is_null($new_order)) {
            $appShieldOrder = ValueTracFactory::generateOrder($this->valueTracService->getOrder($event->data->Id));
        } else
            $appShieldOrder = $new_order;

        // TRANSFORM THE AppraisalShield ORDER PRODUCTS TO JUST THEIR NAMES
        $products = array_map(function ($el) {
            return $el->form;
        }, (array)$appShieldOrder->products);

        $placeholder = '';
        foreach ($products as $p) {
            $placeholder .= "'{$p}',";
        }
        $placeholder = rtrim($placeholder, ',');

        $query = "SELECT * FROM products WHERE description in ({$placeholder})";

        $mappedProducts = $this->appShieldPdo->query($query);

        $purchaseType = 'Other';
        $appraisalPurchaseType = utility\StringExt::minify($appShieldOrder->appraisal_type);
        if (!isset($purchaseTypeValues[$appraisalPurchaseType])) {
            $this->log->error("Missing Appraisal Loan Type. (" . $appShieldOrder->appraisal_type . ")");
        } else {
            $purchaseType = $purchaseTypeValues[$appraisalPurchaseType];
        }

        // MAP AppraisalShield LenderID to HomeBase LenderID
        $hbam_lender = $this->appShieldPdo->query("SELECT homebase_lender_id from lender_map where appShield_lender_id = ?", [$appShieldOrder->lender_id]);

        if (!empty($hbam_lender)) {
            $hbam_lender_id = $hbam_lender[0]->homebase_lender_id;
        } else {
            $hbam_lender_id = null;
        }

        // BASE DATA
        $data = [
            'property_address' => $appShieldOrder->property_street,
            'property_city' => $appShieldOrder->property_city,
            'property_state' => $appShieldOrder->property_state,
            'property_zip' => $appShieldOrder->property_zip,
            'lender_loan_number' => $appShieldOrder->loan_number,
            'loan_type' => $appShieldOrder->loan_type,
            'purchase_type' => $purchaseType,
            'lender_id' => $hbam_lender_id,
            'appraisal_time_request' => 1,
            'source' => 'appraisalShield',
            'borrowers' => [],
            'parties' => [],
            'products' => []
        ];

        if (!is_null($appShieldOrder->agency_case_number)) {
            $data['fha_num'] = $appShieldOrder->agency_case_number;
        }

        // ADD BORROWERS IN HOMEBASE BORROWER FORMAT
        array_push($data['borrowers'], $this->checkContactInfo([
            'name' => $appShieldOrder->borrower_name,
            'email' => $appShieldOrder->borrower_email,
            'home' => $appShieldOrder->borrower_phone,
            'mobile' => $appShieldOrder->borrower_cell,
            'entry' => TRUE,
            'send_report' => FALSE
        ]));

        array_push($data['borrowers'], $this->checkContactInfo([
            'name' => $appShieldOrder->co_borrower_name,
            'email' => $appShieldOrder->co_borrower_email,
            'home' => $appShieldOrder->co_borrower_phone,
            'mobile' => $appShieldOrder->co_borrower_cell,
            'entry' => TRUE,
            'send_report' => FALSE
        ]));

        // ADD PRODUCTS USING PRODUCT MAPPING
        $data['products'] = [];
        foreach ($mappedProducts as $mappedProduct) {
            $this->addProducts($data, $mappedProduct->homebase_product_maps);
        }

        if ($is_child == true) {
            $hbam_order_id = $this->appShieldPdo->query("select homebase_order_id from orders where appraisal_shield_id = ? order by created_at limit 1", [$event->data->encryptedAppraisalID]);
            $data['isChild'] = true;
            $data['parent_order_id'] = $hbam_order_id[0]->homebase_order_id;
        }

        $client = $this->createHomebaseGuzzleClient();

        try {
            $response = $client->post('order', ['json' => $data]);
        } catch (ClientException $exception) {
            // insert into queue
            $this->appShieldPdo->query("
            INSERT INTO queue (event_type, payload, fail_type, sent_at) 
            VALUES (:eventtype, :payload, :fail, :sent)
            ON DUPLICATE KEY UPDATE payload = :payload", [
                ':eventtype' => $event->type,
                ':payload' => $event->raw,
                ':fail' => 'error',
                ':sent' => $event->time
            ]);

            $this->throwClientException($exception);
            return null;
        }

        // ADD LINKING
        $payload = json_decode($response->getBody());

        $this->appShieldPdo->query("INSERT INTO orders (
                    appraisal_shield_id,
                    homebase_order_id,
                    raw
                ) VALUES (?, ?, ?)", [
            $appShieldOrder->id,
            $payload->appraisal_id,
            json_encode($appShieldOrder)
        ]);

        // AUTO ACCEPT ORDER
        if (!$is_child)
            $this->valueTracService->acceptOrder($appShieldOrder->id);

        $order = (object)[
            'id' => $payload->appraisal_id
        ];

        return $order;
    }

    public function addNewNote(Event $event)
    {
        $homebaseOrderIds = self::fetchHomebaseOrderID($event, true);

        if (gettype($homebaseOrderIds) == 'array') {
            foreach ($homebaseOrderIds['results'] as $o) {
                $cmnt = $this->parseComment("Comment Added from ({$event->data->fromName}): {$event->data->note}", array_column($homebaseOrderIds['results'], 'id'));
                $this->comment($cmnt, $o->id);
            }
        } else {
            $this->comment("Comment Added from ({$event->data->fromName}): {$event->data->note}", $homebaseOrderIds->id);
        }
    }

    public function addNewDocument(Event $event)
    {
        $homebaseOrderId = self::fetchHomebaseOrderId($event);
        $file = $this->valueTracService->getDocument($event->data->encryptedAppraisalID, $event->data->encryptedDocumentID);
        if (gettype($homebaseOrderId) == 'array') {
            $homebaseOrderId = $homebaseOrderId[0]->id;
        } else if (gettype($homebaseOrderId) == 'object') {
            $homebaseOrderId = $homebaseOrderId->id;
        }

        $arr = explode('.', $event->data->documentFilename);
        $len = count($arr);
        $ext = 'pdf';
        if ($len > 1) {
            $ext = $arr[$len - 1];
        }
        self::uploadFileToHomebase($homebaseOrderId, $file, $ext); // file we receive is already base64 encoded

    }

    public function toggleHold(Event $event)
    {
        $homebaseOrderIds = self::fetchHomebaseOrderId($event, true);
        $payload = [];
        if ($event->type == HOLD_PLACED) {
            $event->data->note = 'The Lender has put this order on Hold';
        }

        if ($event->type == HOLD_REMOVED) {
            $event->data->note = 'The Lender has removed this order from Hold';
        }

        if (gettype($homebaseOrderIds) == 'array') {
            foreach ($homebaseOrderIds['results'] as $o) {
                $cmnt = $this->parseComment("{$event->data->note}", array_column($homebaseOrderIds['results'], 'id'));
                $this->comment($cmnt, $o->id);
            }
        } else {
            $this->comment("Comment Added from ({$event->data->fromName}): {$event->data->note}", $homebaseOrderIds->id);
        }
    }

    public function cancelOrder(Event $event)
    {
        $homebaseOrderIds = self::fetchHomebaseOrderId($event, true);
        $event->data->note = 'The Lender has requested to cancel this order';
        if (gettype($homebaseOrderIds) == 'array') {
            foreach ($homebaseOrderIds['results'] as $o) {
                $cmnt = $this->parseComment("{$event->data->note}", array_column($homebaseOrderIds['results'], 'id'));
                $this->comment($cmnt, $o->id);
            }
        } else {
            $this->comment("{$event->data->note}", $homebaseOrderIds->id);
        }
    }

    public function orderChange(Event $event)
    {
        $hbamOrders = $this->fetchHomebaseOrderId($event, true);
        // Get Old Order
        $oldOrder = $this->appShieldPdo->query("SELECT raw from orders where appraisal_shield_id = ?", [$event->data->encryptedAppraisalID]);
        $oldOrder = $oldOrder[0]->raw;

        // GATHER AppraisalShield ORDER FROM SERVICE
        $newOrder = ValueTracFactory::generateOrder($this->valueTracService->getOrder($event->data->encryptedAppraisalID));
        $newOrder = json_encode($newOrder);

        $differences = ObjectCompare::compareOrderForms($oldOrder, $newOrder);

        $newOrder = utility\ObjectExt::toObject($newOrder);
        $oldOrder = utility\ObjectExt::toObject($oldOrder);

        if (!empty($differences->additionalForms) || !empty($differences->removedForms) || !empty($differences->feeChanged)) {
            $comment = 'The Lender has made the following changes in the Forms';

            if (!empty($differences->additionalForms)) {
                $comment .= '<br>- <b>Addition of Products:</b> ';

                foreach ($differences->additionalForms as $key => $value) {
                    foreach ($newOrder->products as $newForm) {
                        if ($newForm->appraisal_form_id == $key) {
                            if ($this->checkIfWeWantToCreateNewOrder($newForm->form)) {
                                $old_products = $oldOrder->products;
                                $this->removeOldProduct($old_products, $newOrder);
                                $newOrder = utility\ObjectExt::toObject($newOrder);
                                $this->createOrder($event, true, $newOrder);
                                $newOrder = utility\ObjectExt::toObject($newOrder);
                                $new_products = $newOrder->products;
                                $formids = array_map(function ($arr) {
                                    return $arr->appraisal_form_id;
                                }, $new_products);
                                $formsInDB = implode(',', $formids);
                            } else {
                                $formsInDB .= $newForm->appraisal_form_id;
                            }
                            $comment .= $newForm->form . ', ';
                        }
                    }
                }

                $comment = rtrim(trim($comment), ',');
            }

            if (!empty($differences->removedForms)) {
                $comment .= '<br>- <b>Removal of Products:</b> ';

                foreach ($differences->removedForms as $form) {
                    $oldOrder = utility\ObjectExt::toObject($oldOrder);
                    foreach ($oldOrder->products as $oldForm) {
                        if ($oldForm->appraisal_form_id == $form) {
                            $formsInDB = str_replace($oldForm->appraisal_form_id . ',', '', $formsInDB);
                            $comment .= $oldForm->form . ', ';
                        }
                    }
                }

                $comment = rtrim(trim($comment), ',');
            }

            if (!empty($differences->feeChanged)) {
                $comment .= '<br>- <b>Fee Changed of </b> ';

                foreach ($differences->feeChanged as $key => $value) {
                    $oldOrder = utility\ObjectExt::toObject($oldOrder);
                    foreach ($oldOrder->products as $form) {
                        if ($form->appraisal_form_id == $key) {
                            $comment .= $form->form . ' to $' . $value . ', ';
                        }
                    }
                }

                $comment = rtrim(trim($comment), ',');
            }

            if (gettype($hbamOrders) == 'array') {
                foreach ($hbamOrders['results'] as $o) {
                    $cmnt = $this->parseComment($comment, array_column($hbamOrders['results'], 'id'));
                    $this->comment($cmnt, $o);
                }
            } else {
                $this->comment($comment, $hbamOrders->id);
            }

            $this->appShieldPdo->query("update orders set raw = ? where appraisal_shield_id = ?", [json_encode($newOrder), $newOrder->id]);
        }
    }

    private function fetchHomebaseOrderId(Event $event, $returnAll = false)
    {
        try {
            $result = $this->appShieldPdo->query("SELECT * from orders where appraisal_shield_id = ?", [$event->data->encryptedAppraisalID]);

            if (is_array($result) && count($result) > 1) {
                $order_json = json_decode($result[count($result) - 1]->raw);
                $products = json_decode(json_encode($order_json->products), true);
                if (self::checkIfWeWantToCreateNewOrder(implode(',', array_column($products, 'form')))) {
                    $result = $this->appShieldPdo->query("SELECT * FROM orders WHERE appraisal_shield_id = :appid order by created_at desc", [':appid' => $event->data->encryptedAppraisalID]);
                    if ($returnAll) {
                        $res_arr = [];
                        foreach ($result as $res) {
                            $query_result = $this->homebasePdo->query("SELECT * FROM appraisals WHERE id = ?", [$res->homebase_order_id]);
                            array_push($res_arr, $query_result[0]);
                        }
                        return ['result' => $res_arr[0], 'results' => $res_arr];
                    }
                }
            }

            $result = $this->homebasePdo->query("SELECT * FROM appraisals WHERE id = ?", [$result[0]->homebase_order_id]);
            return $result[0];
        } catch (ClientException $exception) {
            $this->throwClientException($exception);
        }
    }

    private function triggerHomebaseEvent(string $event, $homebaseOrderId, array $payload)
    {
        $client = $this->createHomebaseGuzzleClient();
        try {
            if (!empty($payload)) {
                $_Payload = [
                    'event' => $event,
                    'id' => $homebaseOrderId
                ];
                if (!empty($payload)) {
                    $_Payload['data'] = $payload;
                }
                $client->post('event', ['json' => $_Payload]);
            }

        } catch (ClientException $exception) {
            throw new \Exception($exception->getResponse()->getBody()->getContents());
        }
    }

    private function uploadFileToHomebase($homebaseOrderId, string $base64encoded_file, string $ext = 'pdf')
    {
        $client = $this->createHomebaseGuzzleClient();
        try {
            if (!empty($base64encoded_file)) {
                $_Payload = [
                    'id' => $homebaseOrderId
                ];
                if (!empty($base64encoded_file)) {
                    $_Payload['file'] = $base64encoded_file;
                }

                $_Payload['ext'] = $ext;

                $client->post('file', ['json' => $_Payload]);
            }

        } catch (ClientException $exception) {
            throw new \Exception($exception->getResponse()->getBody()->getContents());
        }
    }

    private function checkContactInfo($consumer)
    {
        // BACKUP NUMBER DEFAULT
        $backupNumber = 'N\A';

        // BACKUP NUMBER SET TO HOME FIRST, THEN MOBILE IF VALUES EXIST
        if (!empty($consumer['home'])) $backupNumber = $consumer['home'];
        if (!empty($consumer['mobile'])) $backupNumber = $consumer['mobile'];

        // IF EITHER HOME OR MOBILE IS EMPTY USE BACKUP NUMBER
        if (empty($consumer['home'])) $consumer['home'] = $backupNumber;
        if (empty($consumer['mobile'])) $consumer['mobile'] = $backupNumber;

        return $consumer;
    }

    private function addProducts(&$data, $products)
    {
        $splitProducts = explode(',', $products);
        if (!is_array($splitProducts)) $splitProducts = [$splitProducts];

        $data['products'] = array_merge($data['products'], $splitProducts);
    }

    private function comment($message, $id)
    {
        // INSERT COMMENT ON ORDER
        $this->homebasePdo->query("
INSERT INTO comments (appraisal_id, comment_name, type, role, comment, created_at, `read`, marked_read, time_marked,
                      important_flag, action, updated_at)
                        VALUES (:id, 'Api Lender Message', 'Lender', 'USER', :comment, now(), FALSE, NULL, NULL, NULL, FALSE, now())", [
            ':id' => $id,
            ':comment' => $message
        ]);
    }

    /**
     * Parse comment for parent child orders
     * @param string $comment comment you want to post
     * @param array $arr list of all related hbam order_ids
     * @return string
     */
    private function parseComment(string $comment, array $arr)
    {
        $tmp = '<p> This comment may pertain to Order ID ';
        foreach ($arr as $a) {
            $tmp .= '<a target="_blank" href="/search?id=' . $a . '">' . $a . '</a>, ';
        }
        $tmp = trim($tmp, ', ');
        $tmp .= '</p>';
        $tmp .= '<p>' . $comment . '</p>';
        return $tmp;
    }

    /**
     * Method removes the old products from the new event object
     * @param array $old_forms
     * @param object $newOrder
     */
    private function removeOldProduct(array $old_forms, object &$newOrder)
    {
        if (gettype($newOrder) == 'object') {
            $new_order = $newOrder;
        } else {
            $new_order = json_decode($newOrder);
        }
        $new_products = $new_order->products;
        $form_ids = array_column($old_forms, 'appraisal_form_id');
        $filtered_products = [];
        foreach ($form_ids as $id) {
            $new_products = array_filter($new_products, function ($arr) use ($id) {
                return $arr->appraisal_form_id != $id;
            }, ARRAY_FILTER_USE_BOTH);

            $filtered_products = $new_products;
        }
        if (count($filtered_products) > 0) {
            $new_order->products = $filtered_products;
            $newOrder = json_encode($new_order);
        }
    }

    /**
     * checks weather we want to create new order in homebaseamc
     * some product/form types are separate order in homebase which is not the case in AppraisalShield
     * @param $formType string with ',' seperated products or single product
     * @return bool
     */
    private function checkIfWeWantToCreateNewOrder(string $formType): bool
    {
        $query = "select homebase_product_maps, description from products where description = ";
        $params = [];

        if (str_contains($formType, ',')) {
            $str_arr = explode(',', $formType);
            foreach ($str_arr as $str) {
                $query .= '?';
                array_push($params, $str);
            }
        } else {
            $query .= '?';
            array_push($params, $formType);
        }

        $resp = $this->appShieldPdo->query($query, $params);

        $products = [
            '1004D',
            '1004Drecert',
            '2000',
            '2000A',
            '2006B',
            'deskReview'
        ];

        if (count($resp) > 0) {
            foreach ($resp as $r) {
                $value = array_search($r->homebase_product_maps, $products);
                $type = gettype($value);
                if ($type == 'integer' && $value > -1) {
                    return true;
                }
            }
            return false;
        } else {
            return false;
        }
    }

    private function additionalCommentToProductSelection($products, $order)
    {
        if (in_array('5e5c16cc0082850c3553be42', $products) ||
            in_array('5e5c16ce0082850c3553be48', $products) ||
            in_array('5e78b84e00828517bd1945cf', $products) ||
            in_array('5e5c16d50082850c3553be72', $products)) {
            $this->comment('Client ordered only 216: please remember to adjust the fees for the order and remove the 1007.', $order);
        }
    }
}