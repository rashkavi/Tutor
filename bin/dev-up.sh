#!/usr/bin/env bash
# Local test setup: start MariaDB and the PHP dev server on 127.0.0.1:8082 with e-mail / WhatsApp written to
# storage/messages.log instead of being sent (what the e2e tests expect). Safe to run again.
cd "$(dirname "$0")/.." || exit 1
mysqladmin ping >/dev/null 2>&1 || mysqladmin -uroot -p"${DB_ROOT_PASSWORD:-}" ping >/dev/null 2>&1 || { (nohup mysqld_safe >/dev/null 2>&1 &); for i in $(seq 1 30); do mysqladmin -uroot -p"${DB_ROOT_PASSWORD:-}" ping >/dev/null 2>&1 && break; sleep 1; done; }
if ! curl -s -o /dev/null http://127.0.0.1:8082/; then
  (MAIL_DRIVER=log WHATSAPP_DRIVER=log nohup php -S 127.0.0.1:8082 bin/dev-router.php > /tmp/enlixo-dev-server.log 2>&1 &)
  sleep 1
fi
curl -s -o /dev/null -w "server: %{http_code}\n" http://127.0.0.1:8082/
