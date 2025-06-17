<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize variables
$formSubmitted = false;
$success = false;
$error = '';

// Database configuration - USE DEFAULT XAMPP CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Default XAMPP username
define('DB_PASS', '');           // Default XAMPP password (empty)
define('DB_NAME', 'contact_form_system');

// Process form if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formSubmitted = true;
    
    // Verify reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $secret_key = '6Ldi4GMrAAAAAP_Gns1gBMm13Ap_tbDbB_7xiW9S';
    
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result);
    
    if ($response && $response->success) {
        $name = htmlspecialchars($_POST['name'] ?? '');
        $email = htmlspecialchars($_POST['email'] ?? '');
        $message = htmlspecialchars($_POST['message'] ?? '');
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        try {
            // Connect using defined constants
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($db->connect_error) {
                throw new Exception("Connection failed: " . $db->connect_error);
            }
            
            // Include ip_address in the query
            $stmt = $db->prepare("INSERT INTO submissions (name, email, message, ip_address) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $db->error);
            }
            
            $stmt->bind_param("ssss", $name, $email, $message, $ip_address);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $stmt->close();
            $db->close();
        } catch (Exception $e) {
            $error = "Database error occurred: " . $e->getMessage();
            error_log($error);
            
            // Show detailed error on localhost
            if ($_SERVER['HTTP_HOST'] === 'localhost') {
                $error .= " - Full error: " . $e->getMessage();
            }
        }
    } else {
        $error = "reCAPTCHA verification failed";
        if ($response && !empty($response->{'error-codes'})) {
            $error .= " (Error codes: " . implode(", ", $response->{'error-codes'}) . ")";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Contact Form with reCAPTCHA</title>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .error { color: #d9534f; font-size: 0.9em; margin-top: -10px; margin-bottom: 10px; }
        input:invalid, textarea:invalid { border-color: #d9534f; }
        input:valid, textarea:valid { border-color: #5cb85c; }
        .success-message { 
            color: #5cb85c; 
            padding: 20px; 
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #5cb85c;
            border-radius: 5px;
            max-width: 600px;
            margin: 20px auto;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if ($formSubmitted && $success): ?>
            <div class="success-message">
                <h2>Thank You!</h2>
                <p>Your message has been successfully submitted.</p>
                <p><a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Submit another message</a></p>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error" style="text-align: center; padding: 10px; margin-bottom: 20px; background: #ffeeee; border: 1px solid #ffcccc;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="demo-form" novalidate>
                <h2 style="text-align: center;">Contact Form</h2>
                
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" id="name" name="name" 
                           pattern="[A-Za-z ]{2,50}" 
                           title="Only letters and spaces (2-50 characters)"
                           required
                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    <div id="name-error" class="error"></div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" 
                           pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$"
                           title="Please enter a valid email address"
                           required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <div id="email-error" class="error"></div>
                </div>
                
                <div class="form-group">
                    <label for="message">Message:</label>
                    <textarea id="message" name="message" 
                              minlength="10" maxlength="500"
                              title="Message must be 10-500 characters"
                              required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    <div id="message-error" class="error"></div>
                </div>
                
                <div class="form-group">
                    <div class="g-recaptcha" 
                         data-sitekey="6Ldi4GMrAAAAAFeab8PnC1YRVCqt8ums_oSsw2sT"
                         data-callback="onSubmit"></div>
                    <div id="recaptcha-error" class="error"></div>
                </div>
                
                <input type="submit" name="submit" value="Submit">
            </form>

            <script>
            document.getElementById('demo-form').addEventListener('submit', function(e) {
                let isValid = true;
                
                document.querySelectorAll('.error').forEach(el => el.textContent = '');
                
                // Name validation
                const name = document.getElementById('name');
                if (!name.checkValidity()) {
                    document.getElementById('name-error').textContent = 
                        'Please enter a valid name (2-50 letters and spaces only)';
                    isValid = false;
                }
                
                // Email validation
                const email = document.getElementById('email');
                if (!email.checkValidity()) {
                    document.getElementById('email-error').textContent = 
                        'Please enter a valid email address';
                    isValid = false;
                }
                
                // Message validation
                const message = document.getElementById('message');
                if (!message.checkValidity()) {
                    document.getElementById('message-error').textContent = 
                        'Message must be between 10-500 characters';
                    isValid = false;
                }
                
                // reCAPTCHA validation
                if (grecaptcha.getResponse().length === 0) {
                    document.getElementById('recaptcha-error').textContent = 
                        'Please complete the reCAPTCHA verification';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });

            function onSubmit(token) {
                if (document.getElementById('demo-form').checkValidity()) {
                    document.getElementById('demo-form').submit();
                }
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>