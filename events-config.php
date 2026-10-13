<?php
/**
 * Events admin settings.
 *
 * The password is stored as a PBKDF2-SHA256 hash, never in plain text.
 *
 * Current password: pioneer2026   <-- change this before staff start using it.
 *
 * To set a new one, open /events-admin.php?newhash=YOUR-NEW-PASSWORD while
 * logged in; it prints the three lines to paste back in here.
 */

return [
    'pw_salt' => 'c4b267d75c3ebda303a42cc2d3abecda',
    'pw_iter' => 120000,
    'pw_hash' => '931b8be580584fed41b7272b523a0990772459ebd0d8c6a3bb5dd8ab6e1b54a8',

    'session_name' => 'pioneer_events_admin',
    'data_file'    => __DIR__ . '/events.json',
    'upload_dir'   => __DIR__ . '/assets/img/events',
    'upload_url'   => 'assets/img/events',
];
