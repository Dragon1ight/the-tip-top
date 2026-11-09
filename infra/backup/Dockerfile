FROM alpine:3.20
RUN apk add --no-cache restic mariadb-client git curl tzdata bash
COPY backup.sh restore-test.sh entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/*.sh
CMD ["/usr/local/bin/entrypoint.sh"]
