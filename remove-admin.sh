#!/usr/bin/env bash

# Run this on the server to remove an admin. 
# Do this only after running deploy.sh to setup the database. 

readonly CONNECTION_INFO_FILE=mysqlinfo.php

# Variables used by script
FORCE=0
USERNAME=""

usage() { echo "Usage: ${0} -u username" 1>&2; exit 0; }

# Get user input
while getopts "hu:" o; do
    case "${o}" in
        h)
            usage
            ;;
        u)
            USERNAME=$OPTARG
            ;;
        *)
            usage
            ;;
    esac
done
shift $((OPTIND-1))

# Check if username provided by script call
if [ -z ${USERNAME} ]; then
    printf "%s: Error: Please use the -u flag to specify a username for the admin\n" ${0} >&2
    exit 1
fi

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
USER_EXISTS=$(printf "SELECT * FROM ${dbname}.admins WHERE username = \"${USERNAME}\";\n" | mysql --user=${dbusername} --password=${dbpassword} | wc -l)
if [ ! $USER_EXISTS -gt 0 ]; then
    printf "%s: Error: Mysql user \"%s\" does not exist.\n" ${0} ${USERNAME} >&2
    exit 1
fi 

# Delete user
printf "DELETE FROM ${dbname}.admins WHERE username = \"${USERNAME}\";\n" | mysql --user=${dbusername} --password=${dbpassword}

