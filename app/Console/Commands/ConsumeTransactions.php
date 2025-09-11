<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RdKafka\Consumer;
use RdKafka\ConsumerTopic;
use RdKafka\Conf;
use GuzzleHttp\Client;
use FlixTech\SchemaRegistryApi\Registry\PromisingRegistry;
use FlixTech\AvroSerializer\Objects\RecordSerializer;
use AvroSchema;

// Import RdKafka constants
use const RD_KAFKA_OFFSET_BEGINNING;
use const RD_KAFKA_RESP_ERR_NO_ERROR;
use const RD_KAFKA_RESP_ERR__PARTITION_EOF;
use const RD_KAFKA_RESP_ERR__TIMED_OUT;

class ConsumeTransactions extends Command
{
    protected $signature = 'kafka:consume-transactions';
    protected $description = 'Consume transactions from Kafka with Avro deserialization';

    public function handle()
    {
        $this->info("Starting Kafka consumer...");

        $schemaRegistryUrl = 'http://localhost:8081';
        $subject = 'transaction-value';

        // Schema registry client
        $schemaRegistry = new PromisingRegistry(new Client(['base_uri' => $schemaRegistryUrl]));

        // Serializer (untuk deserialization)
        $serializer = new RecordSerializer($schemaRegistry);

        // Kafka configuration
        $conf = new Conf();
        $conf->set('group.id', 'laravel-consumer-group');
        $conf->set('auto.offset.reset', 'earliest');

        $consumer = new Consumer($conf);
        $consumer->addBrokers('localhost:9092');

        $topic = $consumer->newTopic('transactions');
        
        // Start consuming from beginning
        $topic->consumeStart(0, RD_KAFKA_OFFSET_BEGINNING);

        $this->info("Listening for messages...");

        while (true) {
            $message = $topic->consume(0, 1000); // timeout 1000 ms

            if ($message === null) {
                continue;
            }

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    try {
                        // Deserialize message
                        $record = $serializer->decodeMessage($message->payload);
                        $this->info('Received: ' . json_encode($record));
                    } catch (\Exception $e) {
                        $this->error('Failed to decode message: ' . $e->getMessage());
                    }
                    break;
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    $this->info("End of partition reached");
                    break;
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    // ignore
                    break;
                default:
                    $this->error($message->errstr());
                    break;
            }
        }
        
        // Stop consuming when done
        $topic->consumeStop(0);
    }
}
