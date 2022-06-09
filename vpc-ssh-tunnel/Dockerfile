FROM alpine:latest

RUN apk add --no-cache openssh-client

WORKDIR /app

COPY /.ssh /app/cred
RUN chmod 400 /app/cred/tehkey.pem

ADD run-ssh.sh /app

ENTRYPOINT ["/bin/sh", "/app/run-ssh.sh"]