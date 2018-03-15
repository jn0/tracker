FILE = track.php
HTTPD_CONFIGD = /etc/apache2/sites-enabled/

DOC_ROOT = $(shell grep -Rw DocumentRoot $(HTTPD_CONFIGD) | awk '/default/ {print $$NF; nextfile;}')
TARGET = $(shell dirname `find $(DOC_ROOT) -name $(FILE) | tail -n1`)

INSTALL = install

install:	$(FILE)
	$(INSTALL) -t $(TARGET) $^
