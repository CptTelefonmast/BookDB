<?php
$footerRightContent = $footerRightContent ?? '';
?>

    <div class="footer-note">

        <div class="footer-left">
            BookDB v1.1 ©cpttelefonmast 2026
        </div>

        <?php if ($footerRightContent !== ''): ?>
            <div class="footer-right">
                <?php echo $footerRightContent; ?>
            </div>
        <?php endif; ?>

    </div>

</div>

<script src="script.js"></script>

</body>
</html>