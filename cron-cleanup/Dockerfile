FROM alpine:3.20

RUN apk add --no-cache bash findutils coreutils util-linux dcron tzdata su-exec

COPY delete-expired.sh /usr/local/bin/delete-expired.sh
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/delete-expired.sh /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
