# Slim Default Project Template
To make microservices quicker to create, I have written this project template to get you started with Slim as a microservice with basic necessities.

File structure is set up using autoloading - namespaces and file names dictate autoloading, for example, config/Routes.php will be "use config/Routes;" for your use statement.

This project makes use of vlucas/DotEnv which will require you to create at least a blank .env file at the project root although I would advise you to use this to store configurations and passwords that you don't want included in version control.