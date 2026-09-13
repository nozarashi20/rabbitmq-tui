#!/bin/sh

set -eu

admin()
{
    rabbitmqadmin "$@" >/dev/null
}

reset_queue()
{
    name="$1"
    type="$2"

    admin delete queue --name "$name" --idempotently
    admin declare queue \
        --name "$name" \
        --type "$type" \
        --durable true \
        --auto-delete false
}

publish_messages()
{
    queue="$1"
    total="$2"
    sequence=1

    while [ "$sequence" -le "$total" ]; do
        admin publish message \
            --routing-key "$queue" \
            --payload "{\"fixture\":\"$queue\",\"sequence\":$sequence}"
        sequence=$((sequence + 1))
    done
}

reset_queue demo.orders.pending classic
publish_messages demo.orders.pending 24

reset_queue demo.payments.retry quorum
publish_messages demo.payments.retry 7

reset_queue demo.audit.stream stream
publish_messages demo.audit.stream 12

echo 'RabbitMQ demo fixtures are ready.'
