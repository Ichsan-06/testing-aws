<?php

use Illuminate\Support\Facades\Route;
use RdKafka\Producer;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use FlixTech\AvroSerializer\Objects\RecordSerializer;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use FlixTech\AvroSerializer\Objects\SchemaResolvers\FileResolver;
use RdKafka\Conf;

Route::get('/', function () {
    $signature = 'kafka:produce';
    $description = 'Send a test message to Kafka';

    $schemaRegistryUrl = 'https://devkafka-sc.dev.bri.co.id:8181';
    // $schemaRegistryUrl = 'http://localhost:8081';
    $subject = 'OPSC_CLEARINGCREDIT_TESTING_2';

    $username = 'OPS_CONSOLE_SAC';
    $password = 'Pwd&FJEdSh';

    try {
        // Membuat schema registry client
        $schemaRegistry = new PromisingRegistry(
            new Client([
                'base_uri' => $schemaRegistryUrl,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("$username:$password"),
                    'Content-Type'  => 'application/vnd.schemaregistry.v1+json',
                    'Accept'        => 'application/vnd.schemaregistry.v1+json',
                ]
            ])
        );        

        // Membuat serializer dengan auto-register
        $serializer = new RecordSerializer($schemaRegistry, [
            RecordSerializer::OPTION_REGISTER_MISSING_SCHEMAS => true,
            RecordSerializer::OPTION_REGISTER_MISSING_SUBJECTS => true,
        ]);

    

        // Data yang mau dikirim
        $data = [
            'referenceTransaction' => 'REF-123456',
            'userClaim'           => null,
            'timestampStart'      => now()->toIso8601String(),
            'timestampStop'       => null,
            'status'              => 'PENDING',
            'amount'              => '100000.50',
            'type'                => 'TRANSFER',
            'sla'                 => '24H',
            'openBranch'          => 'Jakarta-01',
            'cutOffTime'          => null
        ];   

        // Load schema dari file atau definisikan schema
        $avroSchema = \AvroSchema::parse(file_get_contents(storage_path('avro/transaction-value.avsc')));

        // Serialize data ke Avro binary
        $avroData = $serializer->encodeRecord($subject, $avroSchema, $data);



        // Buat konfigurasi
        $conf = new Conf();

        // Security config
        $conf->set('sasl.username', $username);
        $conf->set('sasl.password', $password);

        // Bootstrap server
        $conf->set('bootstrap.servers', 'kafka1.dev.bri.co.id:9093');

        // Buat producer dengan config
        $producer = new Producer($conf);

        // Buat topic
        $topic = $producer->newTopic('OPSC_CLEARINGCREDIT');

        // Kirim message
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $avroData);

        // Poll untuk trigger delivery report callback
        $producer->poll(0);

        // Flush biar benar-benar terkirim
        $result = $producer->flush(10000); // timeout 10 detik
        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new \RuntimeException("Was unable to flush, messages might be lost!");
        }

        dd($topic);

    } catch (\Throwable $th) {

        dd($th->getMessage());
        throw $th;
    }
});


// Get stesting
Route::get('/testing', function () {
    return 'Hello s 222';
});
