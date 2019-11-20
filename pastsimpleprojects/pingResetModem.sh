#!/usr/bin/env bash
#/usr/local/bin/wap_check

# Script to monitor and restart modem when needed

maxPloss=10 #Maximum percent packet loss before a restart

restart_networking() {
        # Add any commands need to get network back up and running
        curl http://admin:admin@192.168.1.1/Forms/tools_system_1
}

# First make sure we can resolve, otherwise 'ping -w' would hang
if ! $(host -W5 67.225.139.151 > /dev/null 2>&1); then
        #Make a note in syslog
        logger "wap_check: Network connection is down, restarting network ..."
        restart_networking
        exit
fi

# Initialize to a value that would force a restart
# (just in case ping gives an error and ploss doesn't get set)
ploss=101
# now ping for 10 seconds and count packet loss
ploss=$(ping -q -w10 67.225.139.151 | grep -o "[0-9]*%" | tr -d %) > /dev/null 2>&1

if [ "$ploss" -gt "$maxPloss" ]; then
        logger "Packet loss ($ploss%) exceeded $maxPloss, restarting network ..."
        restart_networking
fi