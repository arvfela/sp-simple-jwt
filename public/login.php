<?php
chdir(dirname(__DIR__));

require_once('vendor/autoload.php');

use Zend\Config\Factory;
use Zend\Http\PhpEnvironment\Request;

$request = new Request();
/*
 * Validate that the request was made using HTTP POST method
 */
if ($request->isPost()) {
    /*
     * Simple sanitization
     */
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    
    if ($username && $password) {
        try {
    
            $config = Factory::fromFile('config/config.php', true);
    
            /*
             * Connect to database to validate credentials
             */
            $dsn = 'mysql:host=' . $config->database->host . ';dbname=' . $config->database->name;
    
            $db = new PDO($dsn, $config->database->user, $config->database->password);
            
            /*
             * We will fetch user id and password fields for the given username
             */
            $sql = <<<EOL
            SELECT id,
                   password
            FROM   users
            WHERE  username = ?
EOL;
    
            $stmt = $db->prepare($sql);
            $stmt->execute([$username]);
            $rs = $stmt->fetch();
            
            if ($rs) {
                /*
                 * Password was generated by password_hash(), so we need to use
                 * password_verify() to check it.
                 * 
                 * @see http://php.net/manual/en/ref.password.php
                 */
                if (password_verify($password, $rs['password'])) {
                    
                    /*
                     * Create the token as an array
                     */
                    $data = [
                        'iat'  => time(),                   // Issued at: time when the token was generated
                        'jti'  => uniqid(),                 // Json Token Id: an unique identifier for the token
                        'iss'  => $_SERVER['SERVER_NAME'],  // Issuer
                        'nbf'  => time(),                   // Not before
                        'exp'  => time() + 3600,            // Expire
                        'data' => [                         // Data related to the signer user
                            'userId'   => $rs['id'],    // userid from the users table
                            'userName' => $username,    // User name
                        ]
                    ];
                    
                    /*
                     * Encode the array to a JWT string.
                     * Second parameter is the key to encode the token, keep it secure.
                     * You'll need the exact key to verify the token later.
                     * 
                     * The output string can be validated at http://jwt.io/
                     */
                    header('Content-type: application/json');
                    echo json_encode(['jwt' => JWT::encode($data, $config->jwtKey)]);
                } else {
                    header('HTTP/1.0 401 Unauthorized');
                }
            } else {
                header('HTTP/1.0 404 Not Found');
            }
        } catch (Exception $e) {
            header('HTTP/1.0 500 Internal Server Error');
        }
    } else {
        header('HTTP/1.0 400 Bad Request');
    }
} else {
    header('HTTP/1.0 405 Method Not Allowed');
}
