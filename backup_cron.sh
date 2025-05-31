#!/bin/bash
# Incipos günlük yedekleme için cron script

# Incipos dizinine git
cd /path/to/incipos

# PHP ile yedekleme scriptini çalıştır
php backup_system.php > backup_log.txt 2>&1

# İşlem sonucunu mail olarak gönder (isteğe bağlı)
# mail -s "Incipos Günlük Yedek Raporu" your@email.com < backup_log.txt
