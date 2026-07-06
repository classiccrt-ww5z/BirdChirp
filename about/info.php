<?php require_once "../header.php"; ?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h1 class="fw-bold">About <?php echo $SITE_NAME; ?></h1>
            <hr class="mb-4">
            
            <p class="lead">
                Welcome to <?php echo $SITE_NAME; ?>, a simple microblogging site where you can share 
                what’s on your mind, talk with others, and just have fun!
            </p>

            <section class="mt-4">
                <h3 class="h5 fw-bold">What You Can Do</h3>
                <p class="text-muted">
                    Post updates, reply to people, and join conversations happening in real time.  
                    You can also upload images, embed YouTube videos, and share pretty much anything you’re into.
                </p>
            </section>

            <section class="mt-4">
                <h3 class="h5 fw-bold">Why It Exists</h3>
                <p class="text-muted">
                    <?php echo $SITE_NAME; ?> is just a project built for fun.
                </p>
            </section>

            <hr class="my-5">
            <section class="p-4 bg-light rounded-3 border">
                <h3 class="h5 fw-bold mb-3">Link to Us</h3>
                <p class="small text-muted">Love <?php echo $SITE_NAME; ?>? Put a button on your site to help spread the word!</p>
                
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="p-2 bg-white border rounded">
                        <a href="//<?php echo $_SERVER['HTTP_HOST']; ?>/">
                            <img src="/birdchirpbutton.png" alt="<?php echo $SITE_NAME; ?>" style="image-rendering: pixelated;">
                        </a>
                    </div>

                <div class="input-group">
                    <code class="form-control bg-dark text-info p-3" id="buttonCode">
                        &lt;a href="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/"&gt;&lt;img src="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/birdchirpbutton.png" alt="<?php echo $SITE_NAME; ?>" /&gt;&lt;/a&gt;
                    </code>
                </div>
                <button class="btn btn-sm btn-outline-secondary mt-2" onclick="copyCode()">Copy to Clipboard</button>
            </section>
        </div>
    </div>
</div>

<script>
function copyCode() {
    const code = document.getElementById('buttonCode').innerText;
    navigator.clipboard.writeText(code).then(() => {
        alert("Code copied! Now you can paste it on your site.");
    });
}
</script>

<?php require_once "../footer.php"; ?>