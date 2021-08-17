#!/bin/sh -e
#
# Copyright (c) 2021 Bernhard Froehlich. All rights reserved.
#
# Redistribution and use in source and binary forms, with or without
# modification, are permitted provided that the following conditions are
# met:
#
#  1. Redistributions of source code must retain the above copyright notice
#     this list of conditions and the following disclaimer.
#
# 2. Redistributions in binary form must reproduce the above copyright
#    notice, this list of conditions and the following disclaimer in the
#    documentation and/or other materials provided with the distribution.
#
# 3. Neither the name of the author nor the names of its contributors may be
#    used to endorse or promote products derived from this software without
#    specific prior written permission.
#
# THIS SOFTWARE IS PROVIDED "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES,
# INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
# AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
# COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
# INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
# NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
# DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
# THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
# (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
# THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
#
# MAINTAINER=	portmgr@FreeBSD.org
#
# This tool checks CPE (Common Platform Enumeration) info for all ports in
# the ports tree and tries to find CPE entries for those ports that don't
# have USES=cpe yet.
#
# The sqlite database with CPE entries is generated with a nice tool called
# go-cpe-dictionary (https://github.com/kotakanbe/go-cpe-dictionary).
#
# Usage:
#   [env PORTSDIR=/usr/ports CPEDB=/tmp/cpe.sqlite3] chkcpe.sh [category ...]
#

opt_quiet=false

while getopts vq opt; do
        case "$opt" in
        q)
                opt_quiet=true;;
        ?)
                echo "Usage: $0 [-q] [category ...]"
                exit 2;;
        esac
done

shift $((${OPTIND}-1))

rc=0

$opt_quiet || echo "checking CPE info on ports"

: ${PORTSDIR:=/usr/ports}
: ${CPEDB:=/tmp/cpe.sqlite3}
: ${MAKE:=/usr/bin/make}

cd "${PORTSDIR}"

if [ $# -gt 0 ]; then
    CATEGORIES=`echo $@`
else
    CATEGORIES=`echo [a-z]*`
fi

for category in ${CATEGORIES}; do
    if [ ! -d "${PORTSDIR}/${category}" ]; then continue; fi
    case "${category}" in
        Mk) continue ;;
        Templates) continue ;;
        Tools) continue ;;
        distfiles) continue ;;
        packages) continue ;;
    esac

    $opt_quiet || echo "==> ${category}"

    cd "${PORTSDIR}/${category}"
    PORTS=`echo *`

    for port in ${PORTS}; do
        if [ ! -d "${PORTSDIR}/${category}/${port}" ]; then continue; fi

        cd "${PORTSDIR}/${category}/${port}"

        cpestatus=""
        cpemsg=""

        portname=`${MAKE} -VPORTNAME:tl 2>/dev/null || true`
        portmaintainer=`${MAKE} -VMAINTAINER 2>/dev/null || true`
        portcpestr=`${MAKE} -VCPE_STR 2>/dev/null || true`

        if [ ! -z "${portcpestr}" ]; then
            portcpeproduct=`${MAKE} -VCPE_PRODUCT 2>/dev/null || true`
            portcpevendor=`${MAKE} -VCPE_VENDOR 2>/dev/null || true`

            dbcpefound=`sqlite3 ${CPEDB} "SELECT COUNT(*) FROM products, cpes WHERE product = '${portcpeproduct}' AND vendor = '${portcpevendor}' AND products.productid = cpes.productid"`

            if [ ${dbcpefound} -gt 0 ]; then
                cpestatus="VALID"
                cpemsg="found ${dbcpefound} CPE entries"
            else
                cpestatus="INVALID"
                cpemsg="Vendor:Product ${portcpevendor}:${portcpeproduct} not found in DB"
            fi
        else
            dbcpecandidates=`sqlite3 ${CPEDB} "SELECT GROUP_CONCAT(vendor || ':' || product) FROM (SELECT vendor, product FROM products WHERE product = '${portname}' GROUP BY vendor)"`

            if [ -z "${dbcpecandidates}" ]; then
                cpestatus="UNKNOWN"
            else
                cpestatus="MISSING"
                cpemsg="${dbcpecandidates}"
            fi
        fi

        printf "%-45s\t%-30s\t%s\t%s\n" "${category}/${port}" "${portmaintainer}" "${cpestatus}" "${cpemsg}"
    done
done

return $rc
