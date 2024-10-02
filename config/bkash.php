<?php

return [
    "sandbox"         => env("BKASH_SANDBOX", true),
<<<<<<< HEAD
    "bkash_app_key"     => env("BKASH_APP_KEY", "A4noOVANllKDiOffoCppybX9tc"),
    "bkash_app_secret" => env("BKASH_APP_SECRET", "nt4yuwiKHbrdsUm5bTt6VDGAaSCHWxkVvV4jsVUEpf66D4qCq6Bs"),
    "bkash_username"      => env("BKASH_USERNAME", "01301965156"),
    "bkash_password"     => env("BKASH_PASSWORD", "D%M0}HKyzGA"),
=======
    "bkash_app_key"     => env("BKASH_CHECKOUT_APP_KEY", "5nej5keguopj928ekcj3dne8p"),
    "bkash_app_secret" => env("BKASH_CHECKOUT_APP_SECRET", "1honf6u1c56mqcivtc9ffl960slp4v2756jle5925nbooa46ch62"),
    "bkash_username"      => env("BKASH_CHECKOUT_USER_NAME", "testdemo"),
    "bkash_password"     => env("BKASH_CHECKOUT_PASSWORD", "test%#de23@msdao"),
>>>>>>> b7a69fb35eb55c648714d8a12fd844d0dab88d3a
    "callbackURL"     => env("BKASH_CALLBACK_URL", "http://127.0.0.1:8000"),
    'timezone'        => 'Asia/Dhaka',
];


// APP_TIMEZONE="Asia/Dhaka"
// DEFAULT_LANGUAGE="en"
// BKASH_CHECKOUT_APP_KEY="A4noOVANllKDiOffoCppybX9tc"
// BKASH_CHECKOUT_APP_SECRET="nt4yuwiKHbrdsUm5bTt6VDGAaSCHWxkVvV4jsVUEpf66D4qCq6Bs"
// BKASH_CHECKOUT_USER_NAME="01301965156"
// BKASH_CHECKOUT_PASSWORD="D%M0}HKyzGA"
// FACEBOOK_PAGE_ID="111277854476760"
// BKASH_SANDBOX=false
