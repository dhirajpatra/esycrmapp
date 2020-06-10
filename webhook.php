<?php
$challenge = $_REQUEST['hub_challenge'];
$verify_token = $_REQUEST['hub_verify_token'];

if ($verify_token === '937a4a8c13e317dfd28effdd479cad2f') {
    echo $challenge;
}