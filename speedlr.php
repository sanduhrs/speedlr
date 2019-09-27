#!/usr/bin/env php
<?php

use Ramsey\Uuid\Uuid;

require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

define('SPEEDLR_DEVICE_CREDENTIALS_FILE', __DIR__ . '/device.json');

define('SPEEDLR_SPREADSHEET_ID_FILE', __DIR__ . '/spreadsheet.json');

putenv('GOOGLE_APPLICATION_CREDENTIALS=' . __DIR__ . '/credentials.json');

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->addScope(Google_Service_Drive::DRIVE);

$service = new Google_Service_Sheets($client);
$valueRange = new Google_Service_Sheets_ValueRange();

// Store the spreadsheet id given via parameters in a file.
if (!$spreadsheet = json_decode(file_get_contents(SPEEDLR_SPREADSHEET_ID_FILE))) {
    // Create unique client id.
    $deviceId = Uuid::uuid1();
    if ($argc > 1 && isset($argv[1])) {
        file_put_contents(SPEEDLR_SPREADSHEET_ID_FILE, json_encode([
            'id' => $argv[1],
        ]));
    }
    else {
        die("Please provide a spreadsheet id.");
    }
}

// Create unique client id and register device.
if (!$device = json_decode(@file_get_contents(SPEEDLR_DEVICE_CREDENTIALS_FILE))) {
    // Create unique client id.
    $deviceId = Uuid::uuid1();
    file_put_contents(SPEEDLR_DEVICE_CREDENTIALS_FILE, json_encode([
        'id' => $deviceId,
    ]));

    // Register device.
    $valueRange = new Google_Service_Sheets_ValueRange();
    $valueRange->setValues(["values" => [
        $deviceId
    ]]);
    $response = $service->spreadsheets_values->append(
        $spreadsheet->id,
        'Devices!A1',
        $valueRange,
        ["valueInputOption" => "RAW"]
    );
}

try {
    $speedtest = json_decode(shell_exec('speedtest --json'), FALSE, 512, JSON_THROW_ON_ERROR);

    $serverId = Uuid::uuid1();
    $valueRange->setValues(["values" => [
        $serverId,
        $device->id,
        $speedtest->server->url,
        $speedtest->server->lat,
        $speedtest->server->lon,
        $speedtest->server->name,
        $speedtest->server->country,
        $speedtest->server->cc,
        $speedtest->server->sponsor,
        $speedtest->server->id,
        $speedtest->server->host,
        $speedtest->server->d,
        $speedtest->server->latency,
    ]]);
    $response = $service->spreadsheets_values->append(
        $spreadsheet->id,
        'Server!A1',
        $valueRange,
        ["valueInputOption" => "RAW"]
    );

    $clientId = Uuid::uuid1();
    $valueRange->setValues(["values" => [
        $clientId,
        $device->id,
        $speedtest->client->ip,
        $speedtest->client->lat,
        $speedtest->client->lon,
        $speedtest->client->isp,
        $speedtest->client->isprating,
        $speedtest->client->rating,
        $speedtest->client->ispdlavg,
        $speedtest->client->ispulavg,
        $speedtest->client->ispulavg,
        $speedtest->client->country,
    ]]);
    $response = $service->spreadsheets_values->append(
        $spreadsheet->id,
        'Clients!A1',
        $valueRange,
        ["valueInputOption" => "RAW"]
    );

    $speedtestId = Uuid::uuid1();
    $valueRange->setValues(["values" => [
        $speedtestId,
        $device->id,
        $serverId,
        $clientId,
        $speedtest->download,
        $speedtest->upload,
        $speedtest->ping,
        $speedtest->timestamp,
        $speedtest->bytes_sent,
        $speedtest->bytes_received,
        $speedtest->share,
    ]]);
    $response = $service->spreadsheets_values->append(
        $spreadsheet->id,
        'Speedtest!A1',
        $valueRange,
        ["valueInputOption" => "RAW"]
    );
}
catch(\Exception $e) {
    die($e->getMessage());
}
