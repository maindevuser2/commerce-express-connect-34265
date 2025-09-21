<Ajustar según el entorno para el enrutamiendo en las vistas del admin en:>
    views/admin/config.php
    NOTA: Si no se ajusta el url base pueden haber varios probleams con el funcionamiento del dashboard en las vistas del administrador.

    Ejemplo:
        Url Base: http://localhost:3000/Users/alber/OneDrive/Documents/GitHub/E-commerce/
        Url Fija: index.php?page=admin&action=dashboard

<Ajustar según el entorno para el envio de correos de recuperacion en:>
    controllers/AuthController.php

    //URL BASES
    $baseUrl = "http://localhost:3000/OneDrive/Documents/GitHub/E-commerce/";
    $resetFileUrl = "reset_password.php?token=$token";

<Librerias implementadas>
    Stripe con composer: composer require stripe/stripe-php
    PHPMailer con composer: composer require phpmailer/phpmailer
    Acceder de forma segura a variables delicadas: composer require vlucas/phpdotenv

<Generar clave para poder mandar emails>
    https://myaccount.google.com/apppasswords
    Nota: Cambiar la clave (sin espacios) por la que está en .env

<Editar la ruta base en .env para el envio de correos de recuperacion>
    
    Ejemplo:
        #Server Base URL
        APP_URL=http://localhost:3000/OneDrive/Documents/GitHub/E-commerce


<PROYECTO TERMINADO>