FILE = track.php reload.png reload16.png font-awesome.min.css bootstrap.min.css
HTTPD_CONFIGD = /etc/apache2/sites-enabled/

DOC_ROOT = $(shell grep -Rw DocumentRoot $(HTTPD_CONFIGD) | awk '/default/ {print $$NF; nextfile;}')
TARGET = $(shell dirname `find $(DOC_ROOT) -name $(word 1,$(FILE)) | tail -n1`)

INSTALL = install

install:	$(FILE)
	$(INSTALL) -t $(TARGET) $^
	cp -r fonts $(TARGET)/../
