
#!/bin/sh

CONTAINER=`sudo docker ps|grep dockeresycrm_database_1|awk '{print $1}'`
DB='myapp_07061974'
USER='root'
PASSWORD='ds_@^5435345#1jjh2R_VFFGG'
SQLFILE="$(date +%Y-%m-%d-%H-%M-%S).sql"
BUDIR="./db/backup/${SQLFILE}"
MYSQLDUMP='./mysql-dump/'
DUMPSQL='dump.sql'

sudo docker exec  $CONTAINER /usr/bin/mysqldump -u $USER --password=$PASSWORD $DB > $BUDIR 
cp $BUDIR $MYSQLDUMP${DUMPSQL}

