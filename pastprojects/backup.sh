#!/bin/bash
cd /netmnt/imagesbackup2

FREESPACE=`df /netmnt/imagesbackup2 | tail -1 | awk {'print $4'} | grep -oE [0-9]+`

if [ $FREESPACE -lt $((1024*1024*100)) ]; then
	rsync -a --exclude-from=/scripts/backup_excludes.txt --delete-before /data/crop/ /netmnt/imagesbackup2/
else
	rsync -a --exclude-from=/scripts/backup_excludes.txt /data/crop/ /netmnt/imagesbackup2/
fi



cd /netmnt/imagesbackup

FREESPACE=`df /netmnt/imagesbackup | tail -1 | awk {'print $4'} | grep -oE [0-9]+`

if [ $FREESPACE -lt $((1024*1024*100)) ]; then
	rsync -a --exclude-from=/scripts/backup_excludes.txt --delete-before /data/images_root/ /netmnt/imagesbackup/Images
	rsync -a --exclude-from=/scripts/backup_excludes.txt --delete-before /data/images_archived/ /netmnt/imagesbackup/Archived\ Images
else
	rsync -a --exclude-from=/scripts/backup_excludes.txt /data/images_root/ /netmnt/imagesbackup/Images
	rsync -a --exclude-from=/scripts/backup_excludes.txt /data/images_archived/ /netmnt/imagesbackup/Archived\ Images
fi


cd /

umount /netmnt/imagesbackup2
umount /netmnt/imagesbackup