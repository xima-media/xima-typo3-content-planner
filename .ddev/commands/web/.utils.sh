#!/bin/bash

stashComposerFiles() {
    cp composer.json composer.json.orig
}

restoreComposerFiles() {
    local exit_status=$?
    if [ -f "composer.json.orig" ]; then
        mv composer.json.orig composer.json
        local message='composer.json has been restored.'
        if [ $exit_statusq 0 ]; then
            message green "${message}"
        else
            message red "${message}"
        fi
    fi
}

message() {
    local color=$1
    local message=$2

    case $color in
        red)
            echo -e "\033[31m$message\033[0m"
            ;;
        green)
            echo -e "\033[32m$message\033[0m"
            ;;
        yellow)
            echo -e "\033[33m$message\033[0m"
            ;;
        blue)
            echo -e "\033[34m$message\033[0m"
            ;;
        magenta)
            echo -e "\033[35m$message\033[0m"
            ;;
        cyan)
            echo -e "\033[36m$message\033[0m"
            ;;
        *)
            echo -e "$message"
            ;;
    esac
}
