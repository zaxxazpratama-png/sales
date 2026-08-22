<?php
// Blok akses langsung ke folder credentials via web
http_response_code(403);
exit('Access Denied');
