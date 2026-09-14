# RabbitMQ TUI

## Run the queue overview

Start RabbitMQ with its Management plugin:

```bash
docker compose up -d
```

Compose also loads local demo data:

* `demo.orders.pending` is a classic queue with 24 ready messages;
* `demo.payments.retry` is a quorum queue with 7 ready messages;
* `demo.audit.stream` is a stream with 12 messages;
* `demo.jobs.live` has a low-rate publisher and three consumers, so its rates and
  consumer detail keep changing while the stack runs.

Starting Compose again resets the three static `demo.` queues to those counts.
It does not remove or purge queues outside that fixture set.

RabbitMQ exposes AMQP on `localhost:5672`. Its Management UI and HTTP API are
available at [http://127.0.0.1:15672](http://127.0.0.1:15672) with the local
development credentials `guest` / `guest`.

Run the TUI with:

```bash
symfony console rabbitmq:tui
```

The command reads the Management API URL and credentials from `.env`. Put
machine-specific values in `.env.local`.
