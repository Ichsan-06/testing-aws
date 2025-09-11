<?php

namespace App\Console\Commands;

use RdKafka\Producer;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use FlixTech\AvroSerializer\Objects\RecordSerializer;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use FlixTech\AvroSerializer\Objects\SchemaResolvers\FileResolver;

class KafkaProducerTest extends Command
{
    protected $signature = 'kafka:produce';
    protected $description = 'Send a test message to Kafka';

    public function handle()
    {
        $schemaRegistryUrl = 'https://devkafka-sc.dev.bri.co.id:8181';
        $subject = 'transaction-value';

        $username = 'OPS_CONSOLE_SAC';
        $password = 'Pwd&FJEdSh';

        // Membuat schema registry client
        $schemaRegistry = new PromisingRegistry(
            new Client([
                'base_uri' => $schemaRegistryUrl,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("$username:$password")
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

        // Kirim ke Kafka
        $producer = new Producer();
        $producer->addBrokers('localhost:9092');

        $topic = $producer->newTopic('transactions');
        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $avroData);
        $producer->flush(1000);
    }
}
