<?php

namespace App\Services;

use AvroSchema;
use AvroIODatumReader;
use AvroIOBinaryDecoder;
use AvroStringIO;
use RdKafka\KafkaConsumer;

class KafkaAvroConsumer
{
    protected $consumer;

    public function __construct()
    {
        $conf = new \RdKafka\Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BROKERS', 'localhost:9092'));
        $conf->set('group.id', 'laravel-group');
        $conf->set('auto.offset.reset', 'earliest');

        $this->consumer = new KafkaConsumer($conf);
        $this->consumer->subscribe(["OPSC_CLEARING"]);
    }

    public function consume()
    {
        $schemaJson = file_get_contents(storage_path('avro/UserCreated.avsc'));
        $schema = AvroSchema::parse($schemaJson);

        while (true) {
            $message = $this->consumer->consume(120*1000);

            if ($message->err) {
                echo "Error: {$message->errstr()}\n";
                continue;
            }

            $io = new AvroStringIO($message->payload);
            $decoder = new AvroIOBinaryDecoder($io);
            $reader = new AvroIODatumReader($schema);

            $decoded = $reader->read($decoder);

            print_r($decoded); // hasil PHP array
        }
    }
}
