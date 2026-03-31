<?php
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
?>

<div class="forms-verification<?php p($_['verified'] ? ' forms-verification--success' : ' forms-verification--error'); ?>">
	<h2><?php p($_['headline']); ?></h2>
	<p><?php p($_['message']); ?></p>
</div>

<style>
.forms-verification {
	max-width: 640px;
	margin: 40px auto;
	padding: 24px;
	border-radius: 12px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.forms-verification--success {
	border-inline-start: 6px solid var(--color-success);
}

.forms-verification--error {
	border-inline-start: 6px solid var(--color-error);
}

.forms-verification h2 {
	margin: 0 0 12px;
}

.forms-verification p {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
