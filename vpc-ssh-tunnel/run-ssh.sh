#!/bin/sh
while true; ssh ${SSH_USER}@${SSH_HOST} -p 22 -i /app/cred/${SSH_KEY_NAME} -o "StrictHostKeyChecking no" -4 -fNL 0.0.0.0:${REDIS_PORT}:${REDIS_ENDPOINT}:${REDIS_OUTBOUND_PORT}
do sleep 300; done;