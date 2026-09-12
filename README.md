# RabbitMQ TUI

## Run the queue overview

Start RabbitMQ with its Management plugin:

```bash
docker compose up -d
```

RabbitMQ exposes AMQP on `localhost:5672`. Its Management UI and HTTP API are
available at [http://127.0.0.1:15672](http://127.0.0.1:15672) with the local
development credentials `guest` / `guest`.

Run the TUI with:

```bash
symfony console rabbitmq:tui
```

The command reads the Management API URL and credentials from `.env`. Put
machine-specific values in `.env.local`.
