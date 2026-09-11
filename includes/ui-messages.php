<?php
declare(strict_types=1);

/** Render the shared success notification used by every WIDMS role. */
function renderSuccessMessage(string $message): void
{
    $message = trim($message);
    if ($message === '') {
        return;
    }
    ?>
    <div class="alert alert-success widms-success-message widms-dismissible-alert" role="status">
        <span class="widms-success-message-icon" aria-hidden="true">&#10003;</span>
        <span class="widms-success-message-text"><?= nl2br(htmlspecialchars(t($message), ENT_QUOTES, 'UTF-8')) ?></span>
        <button type="button" class="notification-close" aria-label="<?= htmlspecialchars(t('Close'), ENT_QUOTES, 'UTF-8') ?>">&times;</button>
    </div>
    <?php
}
