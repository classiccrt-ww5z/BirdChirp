<?php
$target = $_GET['url'] ?? '';
if (filter_var($target, FILTER_VALIDATE_URL) === FALSE) {
    die("Invalid request.");
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/1.4.0/css/bootstrap.min.css">

<div  class="modal">
  <div class="modal-header">
    <h3>Leaving Our Website</h3>
  </div>
  <div class="modal-body">
    <p class="lead">You are now leaving to visit an external site:</p>
    <div class="well" style="word-break: break-all;">
        <strong><?= htmlspecialchars($target) ?></strong>
    </div>
    <p>Always verify the URL before entering any personal information or passwords.</p>
  </div>
  <div class="modal-footer">
    <button onclick="window.history.back()" class="btn secondary">Go Back</button>
    <button data-url="<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>" class="btn danger" onclick="window.location.href=this.dataset.url">Proceed to Website</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/1.4.0/js/bootstrap-modal.js"></script>

<script>
  $(document).ready(function() {
    $('#exit-modal').modal({
      backdrop: 'static',
      keyboard: false,
      show: true
    });
  });
</script>