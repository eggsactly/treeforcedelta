#!/usr/bin/env bash

# Run this on the server to list admins. 
# Do this only after running deploy.sh to setup the database. 

readonly CONNECTION_INFO_FILE=mysqlinfo.php


usage() { echo "Usage: ${0}" 1>&2; exit 0; }

# Get user input
while getopts "h" o; do
    case "${o}" in
        h)
            usage
            ;;
        *)
            usage
            ;;
    esac
done
shift $((OPTIND-1))

# See if connection file exists  
if [ ! -f "${CONNECTION_INFO_FILE}" ]; then
    printf "%s: Error: File \"%s\" does not exist.\n" ${0} ${CONNECTION_INFO_FILE} >&2
    printf "%s: Info: Please run deploy.sh before running this script.\n" ${0} >&2
    exit 1
fi

# Retrieve database name 
dbname=$(grep dbname ${CONNECTION_INFO_FILE} | cut -f2 -d'"')
dbusername=$(grep dbusername ${CONNECTION_INFO_FILE} | cut -f2 -d'"')
dbpassword=$(grep dbpassword ${CONNECTION_INFO_FILE} | cut -f2 -d'"')

# Check to see if we have a database name
if [ -z ${dbname} ]; then
    printf "%s: Error: no database name exists.\n" ${0} >&2
    printf "%s: Info: Please run deploy.sh\n" ${0} >&2
    exit 1
fi

# Check to see if user already exists 
printf "SELECT username FROM ${dbname}.admins;\n" | mysql --user=${dbusername} --password=${dbpassword}

