#!/usr/bin/env bash

# Run this on the server to create a new admin. 
# Do this only after running deploy.sh to setup the database. 

readonly RANDOM_GENERATION_FILE=/dev/urandom
readonly CONNECTION_INFO_FILE=mysqlinfo.php
readonly PASSWORD_HASHING=mkpasswd
readonly OVERRIDE_MESSAGE="You may override by running with the -f flag. This will reinitialize the database and wipe all data." 
# Variables used by script
FORCE=0
PASSWORD=""
USERNAME=""

usage() { echo "Usage: ${0} -u username [-p password] [-f]" 1>&2; exit 0; }

# Get user input
while getopts "fhp:u:" o; do
    case "${o}" in
        f)
            FORCE=1
            ;;
        h)
            usage
            ;;
        p)
            PASSWORD=$OPTARG
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

# Check if password hashing command exists 
PASSWORD_HASHING_Exists=$(which ${PASSWORD_HASHING} | wc -l)
if [ ${PASSWORD_HASHING_Exists} -eq 0 ]; then
    printf "%s: Error: %s not installed, please install before running.\n" ${0} ${PASSWORD_HASHING_Exists} >&2
    exit 1
fi 

# Check if username provided by script call
if [ -z ${USERNAME} ]; then
    printf "%s: Error: Please use the -u flag to specify a username for the new admin\n" ${0} >&2
    exit 1
fi

# Generate a user password 
if [ -z ${PASSWORD} ]; then
    if [ -e ${RANDOM_GENERATION_FILE} ]; then
        PASSWORD=$(head -c 16 ${RANDOM_GENERATION_FILE} | base64)
    else
        printf "%s: Error: Password could not be generated automatically because %s does not exist.\n" ${0} ${RANDOM_GENERATION_FILE} >&2
        printf "%s: Info: Please use the -p flag to specify a password for the admin\n" ${0} >&2
        exit 1
    fi
fi

# Hash the password 
PASSWORDHASH=$(${PASSWORD_HASHING} -m sha512crypt -s ${PASSWORD})

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
USER_EXISTS=$(printf "SELECT * FROM ${dbname}.admins WHERE username = \"${USERNAME}\";\n" | mysql --user=${dbusername} --password=${dbpassword}| wc -l)
if [ $USER_EXISTS -gt 0 ]; then
    # Exit setup 
    if [ ${FORCE} -eq 0 ]; then
        printf "%s: Error: Mysql user \"%s\" already exists.\n" ${0} ${USERNAME} >&2
        printf "%s: Info: %s\n" ${0} "${OVERRIDE_MESSAGE}" >&2 
        exit 1
    fi
    # Delete user
    printf "DELETE FROM ${dbname}.admins WHERE username = \"${USERNAME}\";\n" | mysql --user=${dbusername} --password=${dbpassword}
fi 

# Create the new user 
printf "INSERT INTO ${dbname}.admins SET username = \"${USERNAME}\", passwordhash = \"${PASSWORDHASH}\";\n" | mysql --user=${dbusername} --password=${dbpassword}

printf "User \"%s\" created with password: \"%s\"\n" ${USERNAME} ${PASSWORD} 

