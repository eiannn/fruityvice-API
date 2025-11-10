<?php
require_once 'config.php';

class LoginSecurity {
    private $db;
    private $max_login_attempts = 5;
    private $lockout_time = 900;

    public function __construct($db) {
        $this->db = $db;
    }

    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return $input;
    }

    public function checkLoginAttempts($ip = null) {
        if (!$this->db) return true;
        $ip = $ip ?: $this->getClientIP();
        
        $stmt = $this->db->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            if ($result['locked_until'] && strtotime($result['locked_until']) > time()) {
                return false;
            }
            if ($result['attempts'] >= $this->max_login_attempts) {
                return false;
            }
        }
        return true;
    }

    public function recordFailedAttempt($ip = null) {
        if (!$this->db) return;
        $ip = $ip ?: $this->getClientIP();
        
        $stmt = $this->db->prepare("INSERT INTO login_attempts (ip_address, attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()");
        $stmt->execute([$ip]);
    }

    public function resetLoginAttempts($ip = null) {
        if (!$this->db) return;
        $ip = $ip ?: $this->getClientIP();
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    }

    public function createAccount($email, $password, $confirm_password) {
        if (!$this->db) {
            throw new Exception('Database connection failed. Please try again later.');
        }
        
        try {
            // Validate inputs
            if (empty($email) || empty($password) || empty($confirm_password)) {
                throw new Exception('All fields are required');
            }
            
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address');
            }
            
            if ($password !== $confirm_password) {
                throw new Exception('Passwords do not match');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters long');
            }

            if (!preg_match('/[A-Z]/', $password)) {
                throw new Exception('Password must contain at least one uppercase letter');
            }

            if (!preg_match('/[a-z]/', $password)) {
                throw new Exception('Password must contain at least one lowercase letter');
            }

            if (!preg_match('/[0-9]/', $password)) {
                throw new Exception('Password must contain at least one number');
            }
            
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('This email is already registered. Please use a different email or login.');
            }
            
            // Hash password and create account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO admin_users (email, password, created_at) VALUES (?, ?, NOW())");
            
            if ($stmt->execute([$email, $hashed_password])) {
                return [
                    'success' => true, 
                    'message' => 'Account created successfully! You can now login with your credentials.',
                    'email' => $email
                ];
            } else {
                throw new Exception('Failed to create account. Please try again.');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception('A system error occurred. Please try again later.');
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function verifyLogin($email, $password) {
        if (!$this->db) {
            throw new Exception('Database connection failed. Please try again later.');
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, email, password FROM admin_users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if (password_verify($password, $user['password'])) {
                    return $user;
                } else {
                    throw new Exception('Invalid password');
                }
            } else {
                throw new Exception('No account found with this email');
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            throw new Exception('A system error occurred. Please try again later.');
        }
    }

    public function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
}

$security = new LoginSecurity($pdo);
$error = '';
$success = '';

// Check if we're in login or create account mode
$is_create_mode = isset($_GET['mode']) && $_GET['mode'] === 'create';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_create_mode) {
            // Handle account creation
            $email = $security->sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Debug: Check what values are received
            error_log("Create Account - Email: $email, Password length: " . strlen($password) . ", Confirm length: " . strlen($confirm_password));
            
            $result = $security->createAccount($email, $password, $confirm_password);
            
            if ($result['success']) {
                $success = $result['message'];
                // Auto-fill email and switch to login mode after successful creation
                $_SESSION['last_created_email'] = $result['email'];
                $is_create_mode = false;
            }
        } else {
            // Handle login
            $email = $security->sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Debug: Check what values are received
            error_log("Login Attempt - Email: $email, Password length: " . strlen($password));
            
            if (!$security->checkLoginAttempts()) {
                throw new Exception("Too many failed login attempts. Please try again in 15 minutes.");
            }
            
            $user = $security->verifyLogin($email, $password);
            
            if ($user) {
                $security->resetLoginAttempts();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_email'] = $user['email'];
                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['login_time'] = time();
                
                // Clear any temporary session data
                unset($_SESSION['last_created_email']);
                
                header("Location: index.php");
                exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        if (!$is_create_mode) {
            $security->recordFailedAttempt();
        }
    }
}

// Auto-switch to login mode if user just created account
if (isset($_SESSION['last_created_email']) && !$is_create_mode) {
    $preset_email = $_SESSION['last_created_email'];
} else {
    $preset_email = '';
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_create_mode ? 'Create Account - FruitInfo' : 'Admin Login - FruitInfo'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 20px;
            border: 2px solid #e8e8e8;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-create {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-login:hover, .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .brand-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        .security-badge {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            font-size: 0.75rem;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .mode-switch {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .mode-switch:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        .password-toggle {
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .password-toggle:hover {
            color: #667eea;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }
        .strength-weak { background-color: #dc3545; width: 25%; }
        .strength-medium { background-color: #ffc107; width: 50%; }
        .strength-strong { background-color: #28a745; width: 75%; }
        .strength-very-strong { background-color: #20c997; width: 100%; }
        .requirement-list {
            font-size: 0.875rem;
            margin-top: 5px;
        }
        .requirement-met {
            color: #28a745;
        }
        .requirement-unmet {
            color: #6c757d;
        }
    </style>
</head>
<body class="login-container">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="login-card p-5">
                    <div class="text-center mb-5">
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <i class="fas fa-shield-alt fa-2x text-primary me-3"></i>
                            <h1 class="h2 brand-text mb-0">Fruit<span>Info</span></h1>
                        </div>
                        <h2 class="h4 text-dark">
                            <?php echo $is_create_mode ? 'Create Admin Account' : 'Admin Login'; ?>
                        </h2>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-4">
                            <label for="email" class="form-label fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" 
                                       placeholder="admin@fruitinfo.com" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($preset_email); ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 password-field" id="password" name="password" 
                                       placeholder="Enter your password" required>
                                <button type="button" class="input-group-text password-toggle border-start-0" data-target="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if ($is_create_mode): ?>
                            <div class="password-strength mt-2" id="password-strength"></div>
                            <div class="requirement-list" id="password-requirements">
                                <div class="requirement-unmet" data-requirement="length">• At least 6 characters</div>
                                <div class="requirement-unmet" data-requirement="uppercase">• One uppercase letter</div>
                                <div class="requirement-unmet" data-requirement="lowercase">• One lowercase letter</div>
                                <div class="requirement-unmet" data-requirement="number">• One number</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_create_mode): ?>
                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 password-field" id="confirm_password" name="confirm_password" 
                                       placeholder="Confirm your password" required>
                                <button type="button" class="input-group-text password-toggle border-start-0" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="mt-2" id="password-match"></div>
                        </div>
                        <?php endif; ?>

                        <div class="d-grid mb-4">
                            <button type="submit" class="btn <?php echo $is_create_mode ? 'btn-create' : 'btn-login'; ?> btn-lg text-white">
                                <i class="<?php echo $is_create_mode ? 'fas fa-user-plus' : 'fas fa-sign-in-alt'; ?> me-2"></i>
                                <?php echo $is_create_mode ? 'Create Account' : 'Access Admin Panel'; ?>
                            </button>
                        </div>
                    </form>

                    <div class="text-center">
                        <?php if ($is_create_mode): ?>
                            <p class="text-muted mb-2">
                                Already have an account? 
                                <a href="?mode=login" class="mode-switch">Login here</a>
                            </p>
                        <?php else: ?>
                            <p class="text-muted mb-2">
                                Don't have an account? 
                                <a href="?mode=create" class="mode-switch">Create one here</a>
                            </p>
                        <?php endif; ?>
                        
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const passwordField = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        <?php if ($is_create_mode): ?>
        // Password strength checker
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('password-strength');
        const requirements = document.getElementById('password-requirements');
        const matchIndicator = document.getElementById('password-match');

        function checkPasswordStrength(password) {
            let strength = 0;
            const requirementsMet = {
                length: false,
                uppercase: false,
                lowercase: false,
                number: false
            };

            // Length requirement
            if (password.length >= 6) {
                strength += 25;
                requirementsMet.length = true;
            }

            // Uppercase requirement
            if (/[A-Z]/.test(password)) {
                strength += 25;
                requirementsMet.uppercase = true;
            }

            // Lowercase requirement
            if (/[a-z]/.test(password)) {
                strength += 25;
                requirementsMet.lowercase = true;
            }

            // Number requirement
            if (/[0-9]/.test(password)) {
                strength += 25;
                requirementsMet.number = true;
            }

            // Update requirements display
            Object.keys(requirementsMet).forEach(req => {
                const element = requirements.querySelector(`[data-requirement="${req}"]`);
                if (requirementsMet[req]) {
                    element.classList.remove('requirement-unmet');
                    element.classList.add('requirement-met');
                } else {
                    element.classList.remove('requirement-met');
                    element.classList.add('requirement-unmet');
                }
            });

            // Update strength bar
            strengthBar.className = 'password-strength';
            if (strength <= 25) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 50) {
                strengthBar.classList.add('strength-medium');
            } else if (strength <= 75) {
                strengthBar.classList.add('strength-strong');
            } else {
                strengthBar.classList.add('strength-very-strong');
            }
        }

        function checkPasswordMatch() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (!confirmPassword) {
                matchIndicator.innerHTML = '';
                return;
            }

            if (password === confirmPassword) {
                matchIndicator.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</small>';
            } else {
                matchIndicator.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</small>';
            }
        }

        passwordField.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            if (confirmPasswordField.value) {
                checkPasswordMatch();
            }
        });

        confirmPasswordField.addEventListener('input', checkPasswordMatch);
        <?php endif; ?>

        // Remove the problematic form validation that was blocking submission
        document.querySelector('form').addEventListener('submit', function(e) {
            // Let the server-side validation handle it
            // This prevents the JavaScript from blocking form submission
        });
    </script>
</body>

</html>
