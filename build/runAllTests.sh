#!/usr/bin/env bash

ROOT=$(pwd)
EXIT="0"

function printError {
    RED='\033[0;31m'
    NC='\033[0m' # No Color
    printf "\n\n"
    printf "${RED}***${NC}\n"
    printf "${RED}***  Test failed for: $1 ${NC}\n"
    printf "${RED}***  Exit code was: $2 ${NC}\n"
    printf "${RED}***${NC}\n\n"
}


######################
# Run all test first #
######################
sh ./build/runTest.sh "./"
EXIT=$?

# Do not run more tests if the "BUILD_ALL" failed
if  [ "$EXIT" -ne "0" ]; then
    exit $EXIT
fi


###########################
# Run for each components #
###########################
LINES=$(find src -mindepth 2 -maxdepth 4 -type f -name phpunit.xml.dist)
for line in $LINES; do
   # Save the directory name
   DIR=$(dirname $line)

   # Go to that directory
   sh ./build/runTest.sh "$DIR"
   EXITCODE=$?

   # If there is an error, make sure to return it.
   if  [ "$EXITCODE" -ne "0" ]; then
       EXIT=$EXITCODE
       printError $DIR $EXITCODE
   fi

   # Go back to the root
   cd $ROOT
done

echo "Exiting with code: $EXIT"
exit "$EXIT"
