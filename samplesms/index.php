
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Android SMS Gateway</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="container-fluid">

    <div class="row">

        <!-- Sidebar -->

        <div class="col-lg-2 sidebar d-none d-lg-block">

            <h4 class="logo">
                <i class="bi bi-chat-dots-fill"></i>
                SMS Gateway
            </h4>

            <ul class="menu">
                <li class="active">
                    <i class="bi bi-send-fill"></i> Compose
                </li>

                <li>
                    <i class="bi bi-clock-history"></i> History
                </li>

                <li>
                    <i class="bi bi-phone"></i> Devices
                </li>

                <li>
                    <i class="bi bi-gear-fill"></i> Settings
                </li>
            </ul>

        </div>

        <!-- Main -->

        <div class="col-lg-10 p-4">

            <div class="topbar">

                <div>
                    <h2>Compose SMS</h2>
                    <small>Send messages through your Android phone.</small>
                </div>

                <div class="status online">
                    <i class="bi bi-circle-fill"></i>
                    Gateway Online
                </div>

            </div>

            <div class="row mt-4">

                <div class="col-xl-7">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <form id="sms-form" action="send_sms.php" method="POST">

                                <label class="form-label">Contact Number</label>

                                <input type="text"
                                       class="form-control"
                                       name="phone"
                                       placeholder="639171234567"
                                       required>

                                <label class="form-label mt-3">Message</label>

                                <textarea class="form-control"
                                          id="message"
                                          name="message"
                                          rows="7"
                                          maxlength="500"
                                          placeholder="Type your message..."
                                          required></textarea>

                                <div class="message-info mt-2">

                                    <span id="count">0</span>/500

                                    <span id="segments">1 SMS</span>

                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-4 send-btn">
                                    <i class="bi bi-send-fill"></i>
                                    Send Message
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

                <div class="col-xl-5 mt-4 mt-xl-0">

                    <div class="card shadow-sm border-0">

                        <div class="card-body">

                            <h5>
                                <i class="bi bi-clock-history"></i>
                                Recent Activity
                            </h5>

                            <div class="activity">

                                <div class="placeholder-item"></div>
                                <div class="placeholder-item"></div>
                                <div class="placeholder-item"></div>
                                <div class="placeholder-item"></div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<div class="modal fade" id="resultModal" tabindex="-1" aria-labelledby="resultModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resultModalLabel">SMS Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="resultContent"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>

</body>
</html>