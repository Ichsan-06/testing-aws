<?php

namespace App\Services;

use AvroSchema;
use AvroIODatumWriter;
use AvroIOBinaryEncoder;
use AvroStringIO;
use RdKafka\Producer;

class KafkaAvroProducer
{
    protected $producer;
    protected $topic;

    public function __construct()
    {
        $conf = new \RdKafka\Conf();
        $conf->set('bootstrap.servers', env('KAFKA_BROKERS', 'localhost:9092'));

        $this->producer = new Producer($conf);
        $this->topic = $this->producer->newTopic("user-topic");
    }

    public function produce(array $data)
    {
        $schemaJson = file_get_contents(storage_path('avro/UserCreated.avsc'));
        $schema = AvroSchema::parse($schemaJson);

        $writer = new AvroIODatumWriter($schema);
        $io = new AvroStringIO();
        $encoder = new AvroIOBinaryEncoder($io);

        $writer->write($data, $encoder);
        $message = $io->string();

        $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $message);
        $this->producer->flush(10000);
    }
}
