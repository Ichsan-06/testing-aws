<?php
use FlixTech\AvroSerializer\Objects\RecordSerializer;
use FlixTech\AvroSerializer\Objects\SchemaRegistryClient;
use FlixTech\AvroSerializer\Objects\Serializer\AvroSerializer;
use RdKafka\Producer;

class KafkaProducerService
{
    protected $producer;
    protected $serializer;

    public function __construct()
    {
        // Kafka connection
        $this->producer = new Producer();
        $this->producer->addBrokers("localhost:9092");

        // Schema Registry
        $registry = new SchemaRegistryClient([
            'url' => 'http://localhost:8081' // sesuaikan dengan schema registry kamu
        ]);

        $this->serializer = new RecordSerializer(
            new AvroSerializer($registry)
        );
    }

    public function sendMessage(array $data)
    {
        $topic = $this->producer->newTopic("OPSC_CLEARINGCREDIT");

        // Serialize payload sesuai Avro schema
        $payload = $this->serializer->encodeRecord(
            'com.clearing.credit.incoming.MaintenanceRecord', // namespace + name
            $data
        );

        $topic->produce(
            RD_KAFKA_PARTITION_UA,
            0,
            $payload,
            $data['referenceTransaction'] // key, bisa string
        );

        $this->producer->flush(1000);
    }
}
