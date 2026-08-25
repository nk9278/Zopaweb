<?php
// /index.php
// Primary Root Entry Point for ZopaWeb

// This acts as a secure bootstrap, delegating to the established public router
// without duplicating any logic or circumventing the established application structure.
require __DIR__ . '/public/index.php';
