#!/usr/bin/env bash

# Run this on the server once after cloning the repo to setup the mysql database
# for the site. 

readonly MYSQL_USER=treeforcedelta 
readonly OVERRIDE_MESSAGE="You may override by running with the -f flag. This will reinitialize the database and wipe all data." 
readonly RANDOM_GENERATION_FILE=/dev/urandom
readonly DATABASE_NAME=treeforcedelta_db
readonly CONFIG_SCRIPT=create_database.sql
readonly CONNECTION_INFO_FILE=mysqlinfo.php
readonly AWS_SDK=https://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.zip

SOURCE_DIR="$(pwd)"
TARGET_DIR="/var/www/html"


# Variables used by script
FORCE=0
PASSWORD=""

# Functions 
usage() { echo "Usage: ${0} [-f] [-o target-dir] [-p password]" 1>&2; exit 0; }

# Get user input
while getopts "fhp:o:" o; do
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
        o)
            TARGET_DIR=$OPTARG
            ;;
        *)
            usage
            ;;
    esac
done
shift $((OPTIND-1))

if [[ ! -d "$SOURCE_DIR" ]]; then
    echo "Source directory not found: $SOURCE_DIR"
    exit 1
fi

if [[ ! -d "$TARGET_DIR" ]]; then
    if [ ${FORCE} -eq 0 ]; then
        printf "%s: Error: Target directory not found: %s.\n" ${0} ${TARGET_DIR} >&2
        printf "%s: Info: %s\n" ${0} "${OVERRIDE_MESSAGE}" >&2 
        exit 1
    else
        mkdir -p ${TARGET_DIR} 
    fi
fi



# Check if the config file exists 
if [ -f "${CONNECTION_INFO_FILE}" ]; then
    if [ ${FORCE} -eq 0 ]; then
        printf "%s: Error: %s Already exists, generating again would wipe its contents and replace with a new password.\n" ${0} ${CONNECTION_INFO_FILE} >&2
        printf "%s: Info: %s\n" ${0} "${OVERRIDE_MESSAGE}" >&2 
        exit 1
    fi
fi

# Generate a mysql password 
if [ -z ${PASSWORD} ]; then
    if [ -e ${RANDOM_GENERATION_FILE} ]; then
        PASSWORD=$(head -c 16 ${RANDOM_GENERATION_FILE} | base64)
    else
        printf "%s: Error: Password could not be generated automatically because %s does not exist.\n" ${0} ${RANDOM_GENERATION_FILE} >&2
        printf "%s: Info: Please use the -p flag to specify a password for mysql\n" ${0} >&2
        exit 1
    fi
fi

# See if mysql user already exists 
USER_EXISTS=$(printf "SELECT user FROM mysql.user;\n" | sudo mysql -u root | grep ${MYSQL_USER} | wc -l)
if [ $USER_EXISTS -gt 0 ]; then
    # Exit setup 
    if [ ${FORCE} -eq 0 ]; then
        printf "%s: Error: Mysql user \"%s\" already exists.\n" ${0} ${MYSQL_USER} >&2
        printf "%s: Info: %s\n" ${0} "${OVERRIDE_MESSAGE}" >&2 
        exit 1
    fi
    # If the user uses the force option, delete the user and continue
    $(printf "DROP USER '${MYSQL_USER}'@'localhost';\n" | sudo mysql -u root)
fi 

# Delete the database 
if [ ! ${FORCE} -eq 0 ]; then
    printf "DROP DATABASE IF EXISTS ${DATABASE_NAME};\n" | sudo mysql -u root 
fi

# Configure the mysql helper script
cp ${CONFIG_SCRIPT} ${CONFIG_SCRIPT}.temp
sed -i -e "s/:database/${DATABASE_NAME}/g" ${CONFIG_SCRIPT}.temp

$(printf "source ${CONFIG_SCRIPT}.temp;\n\
USE ${DATABASE_NAME};\n\
CREATE USER '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${PASSWORD}';\n\
GRANT ALL PRIVILEGES ON ${DATABASE_NAME}.* TO '${MYSQL_USER}'@'localhost' WITH GRANT OPTION;\n" | sudo mysql -u root)
rm ${CONFIG_SCRIPT}.temp

# Write the config file 
printf "<?php\n// Database configuration\n\$host = \"localhost\";\n\$dbname = \"treeforcedelta_db\";\n\$dbusername = \"${MYSQL_USER}\";\n\$dbpassword = \"${PASSWORD}\";\n?>\n" > ${CONNECTION_INFO_FILE}

# Edit php.ini to raise the upload file limit to 10M 
sudo find "/etc/php/" -name "php.ini" -exec sed -i -e "s/upload_max_filesize.*/upload_max_filesize = 10M/" {} \;

# Check if the config file exists 
if [ ! -f "${CONNECTION_INFO_FILE}" ]; then
    printf "%s: Error: %s not wirtten. Please check permissions and run this script again.\n" ${0} ${CONNECTION_INFO_FILE} >&2
    exit 1
fi

echo "Deploying to $TARGET_DIR"

find . -name "*.php" -o -name "*.html"  -o -name "*.css" | cpio -updm ${TARGET_DIR}
mkdir -p ${TARGET_DIR}/uploadedImages
chown -R www-data:www-data ${TARGET_DIR}/uploadedImages
chmod 755 ${TARGET_DIR}/uploadedImages

# Download the AWS SDK 
wget $AWS_SDK -P $TARGET_DIR
SDK_NAME=$(basename ${AWS_SDK})
mkdir -p $TARGET_DIR/aws
unzip $TARGET_DIR/$SDK_NAME -d $TARGET_DIR/aws

# Restart Apache2 server 
sudo /etc/init.d/apache2 restart

echo "Deployment complete."

ismbstring_installed=$(dpkg -l | grep php-mbstring | wc -l)

if [ $ismbstring_installed -eq 0 ]; then
    printf "php-mbstring not installed. Not having this prevents transfering images to the digital ocean bucket. Please install it, then re-start apache.\n"
fi 

