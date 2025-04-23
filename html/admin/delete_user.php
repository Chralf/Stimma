<?php
/**
 * Stimma - Lär dig i små steg
 * Copyright (C) 2025 Christian Alfredsson
 * 
 * This program is free software; licensed under GPL v2.
 * See LICENSE and LICENSE-AND-TRADEMARK.md for details.
 * 
 * The name "Stimma" is a trademark and subject to restrictions.
 */
?>

<?php

$user = queryOne("SELECT * FROM " . DB_DATABASE . ".users WHERE id = ?", [$id]);

execute("DELETE FROM " . DB_DATABASE . ".users WHERE id = ?", [$id]); 