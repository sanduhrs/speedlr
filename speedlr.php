#!/usr/bin/env php
<?php

use Ramsey\Uuid\Uuid;

require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

define('SPEEDLR_DEVICE_CREDENTIALS_FILE', __DIR__ . '/device.json');

define('SPEEDLR_DEVICE_CREDENTIALS', []);

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/credentials.json');

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->addScope(Google_Service_Drive::DRIVE);

$service = new Google_Service_Sheets($client);
$spreadsheetId = '16Vhv_vtoQQscbDuWAI2F3AUPKJ-EKoLdDAwm3paaGOs';
$valueRange = new Google_Service_Sheets_ValueRange();

// Create unique client id and register device.
if (!@file_get_contents(SPEEDLR_DEVICE_CREDENTIALS_FILE)) {
    // Create unique client id.
    $device_id = Uuid::uuid1();
    file_put_contents(SPEEDLR_DEVICE_CREDENTIALS_FILE, json_encode([
        'id' => $device_id,
    ]));

    // Register device.
    $valueRange = new Google_Service_Sheets_ValueRange();
    $valueRange->setValues(["values" => [
        $device_id
    ]]);
    $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            'Devices!A1',
            $valueRange,
            ["valueInputOption" => "RAW"]
    );
}

$device = json_decode(file_get_contents(SPEEDLR_DEVICE_CREDENTIALS_FILE));

try {
    $speedtest = json_decode(shell_exec('speedtest --json'), FALSE, 512, JSON_THROW_ON_ERROR);
    print print_r($speedtest, TRUE) . "\n";

    $server_uuid = Uuid::uuid1();
    $valueRange->setValues([
            "values" => array_merge([$server_uuid, $device->id], array_values((array) $speedtest->server))
    ]);
    $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            'Server!A1',
            $valueRange,
            ["valueInputOption" => "RAW"]
    );

    $client_uuid = Uuid::uuid1();
    $valueRange->setValues([
            "values" => array_merge([$client_uuid, $device->id], array_values((array) $speedtest->client))
    ]);
    $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            'Clients!A1',
            $valueRange,
            ["valueInputOption" => "RAW"]
    );

    $speedtest_uuid = Uuid::uuid1();
    $valueRange->setValues(["values" => [
        $speedtest_uuid,
        $device->id,
        $server_uuid,
        $client_uuid,
        $speedtest->download,
        $speedtest->upload,
        $speedtest->ping,
        $speedtest->timestamp,
        $speedtest->bytes_sent,
        $speedtest->bytes_received,
        $speedtest->share,
    ]]);
    $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            'Speedtest!A1',
            $valueRange,
            ["valueInputOption" => "RAW"]
    );
}
catch(\Exception $e) {
    die($e->getMessage());
}
