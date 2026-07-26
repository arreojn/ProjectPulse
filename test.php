<?php
$response = "";
$status = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $recipient = trim($_POST['recipient']);
    $message = trim($_POST['message']);

    if (!empty($recipient) && !empty($message)) {

        $ch = curl_init('https://smsapiph.onrender.com/api/v1/send/sms');

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: sk-2b10zgr5jxlcyhhuovkxglnyo5acopzq',
            'Content-Type: application/json'
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'recipient' => $recipient,
            'message'   => $message
        ]));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $status = "cURL Error: " . curl_error($ch);
        } else {
            $status = "Request sent successfully.";
        }

        curl_close($ch);

    } else {
        $status = "Please complete all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SMS API Test</title>

<style>
body{
    font-family:Arial,Helvetica,sans-serif;
    background:#f5f5f5;
}

.container{
    width:600px;
    margin:40px auto;
    background:#fff;
    padding:20px;
    border-radius:8px;
    box-shadow:0 0 10px rgba(0,0,0,.1);
}

label{
    display:block;
    margin-top:15px;
    font-weight:bold;
}

input, textarea{
    width:100%;
    padding:10px;
    margin-top:5px;
    box-sizing:border-box;
}

textarea{
    height:150px;
    resize:vertical;
}

button{
    margin-top:20px;
    padding:12px 25px;
    background:#007bff;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover{
    background:#0056b3;
}

.success{
    color:green;
    margin-top:20px;
}

.response{
    margin-top:20px;
    background:#f4f4f4;
    padding:15px;
    border-radius:5px;
    white-space:pre-wrap;
}
</style>

</head>
<body>

<div class="container">

<h2>SMS API Test</h2>

<form method="post">

<label>Recipient Number</label>
<input
    type="text"
    name="recipient"
    placeholder="+639168556960"
    value="<?php echo isset($_POST['recipient']) ? htmlspecialchars($_POST['recipient']) : ''; ?>"
    required>

<label>Message</label>
<textarea
    name="message"
    placeholder="Enter your message here..."
    required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>

<button type="submit">Send SMS</button>

</form>

<?php if($status!=""): ?>
<div class="success">
    <?php echo htmlspecialchars($status); ?>
</div>
<?php endif; ?>

<?php if($response!=""): ?>
<h3>API Response</h3>
<div class="response">
<?php
echo htmlspecialchars(
    json_encode(json_decode($response, true), JSON_PRETTY_PRINT)
);
?>
</div>
<?php endif; ?>

</div>

</body>
</html>