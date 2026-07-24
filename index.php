<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/theme_settings.php';

try {
    theme_settings_bootstrap();
} catch (Throwable $exception) {
    // Keep the login screen available even if demo account seeding cannot run.
}

start_session();
$databaseWarning = null;

if (current_user() !== null) {
    redirect(dashboard_path_for_role(current_user()['role'] ?? null));
}

$databaseConnectionOk = true;
try {
    if (!auth_table_exists('users')) {
        $databaseWarning = 'The database schema is not imported yet. Import `database/schema.sql` into MySQL, then reload this page.';
    }
} catch (Throwable $exception) {
    $databaseConnectionOk = false;
    $databaseWarning = 'Unable to connect to the database right now. Check your XAMPP MySQL service and database import.';
}

$errorMessage = null;
$submittedIdentity = '';

if (is_post() && $databaseConnectionOk) {
    $submittedIdentity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (attempt_login($submittedIdentity, $password)) {
        redirect(dashboard_path_for_role(current_user()['role'] ?? null));
    } else {
        $errorMessage = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape(APP_NAME); ?> Login</title>
    <?php echo theme_stylesheet_markup(); ?>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Open+Sans:wght@200;300;400;500;600;700&display=swap");
        * {
          margin: 0;
          padding: 0;
          box-sizing: border-box;
          font-family: "Open Sans", sans-serif;
        }
        body {
          display: flex;
          align-items: center;
          justify-content: center;
          min-height: 100vh;
          width: 100%;
          padding: 0 10px;
        }
        body::before {
          content: "";
          position: absolute;
          width: 100%;
          height: 100%;
          background: url("<?php echo escape(asset_url('assets/images/bg.jpg')); ?>"), #000;
          background-position: center;
          background-size: cover;
        }
        .wrapper {
          width: 100%; /* Adjust for flex container */
          max-width: 400px; /* Keep original max width */
          border-radius: 8px;
          padding: 30px;
          text-align: center;
          border: 1px solid rgba(255, 255, 255, 0.5);
          backdrop-filter: blur(9px);
          -webkit-backdrop-filter: blur(9px);
          z-index: 1; /* Ensure it's above the background */
        }
        .login-container {
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            width: 100%;
            max-width: 1200px;
            gap: 50px; /* Space between logo/title and form */
            z-index: 1;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            padding: 20px;
        }
        .login-branding {
            text-align: center;
            color: #fff;
            max-width: 450px; /* Limit width of branding section */
        }
        .login-branding img {
            width: 250px; /* Adjusted logo for better balance */
            height: auto;
            margin-bottom: 20px;
        }
        .login-branding h1 {
            font-size: 3rem; /* Adjusted title size */
            font-weight: 600;
            margin-bottom: 10px; /* Space between title and tagline */
            letter-spacing: 1px;
        }
        .login-branding h1 strong {
            font-weight: 800; /* Emphasize the acronym */
        }
        .branding-tagline {
            font-size: 1.1rem;
            font-weight: 300;
            line-height: 1.5;
            opacity: 0.9;
        }
        form {
          display: flex;
          flex-direction: column;
        }
        h2 {
          font-size: 2rem;
          margin-bottom: 20px; /* Keep for form title */
          color: #fff;
        }
        .input-field {
          position: relative;
          border-bottom: 2px solid #ccc;
          margin: 15px 0;
        }
        .input-field label {
          position: absolute;
          top: 50%;
          left: 0;
          transform: translateY(-50%);
          color: #fff;
          font-size: 16px;
          pointer-events: none;
          transition: 0.15s ease;
        }
        .input-field input {
          width: 100%;
          height: 40px;
          background: transparent;
          border: none;
          outline: none;
          font-size: 16px;
          color: #fff;
        }
        .input-field input:focus~label,
        .input-field input:valid~label {
          font-size: 0.8rem;
          top: 10px;
          transform: translateY(-120%);
        }
        .forget {
          display: flex;
          align-items: center;
          justify-content: space-between;
          margin: 25px 0 35px 0;
          color: #fff;
        }
        #remember {
          accent-color: #fff;
        }
        .forget label {
          display: flex;
          align-items: center;
        }
        .forget label p {
          margin: 0;
          margin-left: 8px;
          font-size: 0.9rem;
        }
        .wrapper a {
          color: #efefef;
          text-decoration: none;
        }
        .wrapper a:hover {
          text-decoration: underline;
        }
        button {
          background: #fff;
          color: #000;
          font-weight: 600;
          border: none;
          padding: 12px 20px;
          cursor: pointer;
          border-radius: 3px;
          font-size: 16px;
          border: 2px solid transparent;
          transition: 0.3s ease;
        }
        button:hover {
          color: #fff;
          border-color: #fff;
          background: rgba(255, 255, 255, 0.15);
        }
        .register {
          text-align: center;
          margin-top: 30px;
          color: #fff;
        }
        .alert.error {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: .25rem;
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-branding">
            <img src="<?php echo escape(asset_url('assets/images/pulselogo.png')); ?>" alt="<?php echo escape(APP_NAME); ?> Logo" class="login-logo">
            <h1>Project <strong>PULSE</strong></h1>
            <p class="branding-tagline">Portal for Unified Learner Monitoring,<br>School Records and Engagement</p>
        </div>

        <div class="wrapper">
        <form method="post">
            <h2>Login</h2>
            <?php if ($errorMessage !== null): ?>
                <div class="alert error"><?php echo escape($errorMessage); ?></div>
            <?php endif; ?>
            <?php if ($databaseWarning !== null): ?>
                <div class="alert error"><?php echo escape($databaseWarning); ?></div>
            <?php endif; ?>
            <div class="input-field">
                <input id="identity" name="identity" type="text" value="<?php echo escape($submittedIdentity); ?>" required>
                <label for="identity">Enter your Username or Email</label>
            </div>
            <div class="input-field">
                <input id="password" name="password" type="password" required>
                <label for="password">Enter your password</label>
            </div>
            <div class="forget">
                <label for="remember">
                    <input type="checkbox" id="remember">
                    <p>Remember me</p>
                </label>
                <a href="<?php echo escape(route_url('forgot_password.php')); ?>">Forgot password?</a>
            </div>
            <button type="submit" <?php echo !$databaseConnectionOk ? 'disabled' : ''; ?>>Log In</button>
            <div class="register">
                <p>Don't have an account? <a href="#">Register</a></p>
            </div>
        </form>
        </div> <!-- End of .wrapper -->
    </div>
</body>
</html>
