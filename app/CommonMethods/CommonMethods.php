<?php


namespace app\CommonMethods;


/*This class includes only generic common methods that can be used for any type */

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class CommonMethods
{

    /**
     * This function generates a unique token.
     * @param int $length token length
     */
    public function generateRandomString($length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * Function require an array of object and returns the sum of a `column` or `property`.
     * @param array $array
     * @param string $column_name
     * @return int sum
    */
    public function array_column_sum(array $array, string $column_name) : int
    {
        $sum = 0;
        foreach ($array as $key => $value)
        {
            if(isset($value[$column_name]))
                $sum += $value[$column_name];
        }
        return $sum;
    }

    /**
     * Function sends email
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param bool $isHtml
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function send_mail(string $to, string $subject, string $message, bool $isHtml = true)
    {
        $email = new PHPMailer(true);
        try {
            $email->SMTPDebug = SMTP::DEBUG_SERVER;
            $email->isSMTP();
            $email->Host = 'ssl://'.getenv('MAIL_HOST');
            $email->SMTPAuth = true;
            $email->Username = getenv('MAIL_USERNAME');
            $email->Password = getenv('MAIL_PASSWORD');
            $email->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $email->Port = getenv('MAIL_PORT');

            //Recipient
            $email->addAddress($to);

            //Content
            $email->isHTML($isHtml);

            $email->Subject = $subject;
            $email->Body = $message;

            $email->send();
        } catch(\Exception $ex)
        {
            throw $ex;
        }
    }
}