#!/bin/bash

# Set the correct directory
cd "$(dirname "$0")"

BUILD=0
ARGS=""
CMD=""
SQLPROXY=1
WEBPORT=809

while true; do
	case "$1" in
		--build)
			shift
			BUILD=1;
			;;
		--noproxy)
			shift
			SQLPROXY=0;
			;;
		--port)
			shift
			WEBPORT="${1}";
			shift
			;;
		--)
			shift;
			break
			;;
		*)
			break
			;;
	esac
done

if [ "${BUILD}" = "1" ]; then
	docker build --rm -t moneytracker .
fi;

CMD="${@}"

if [ "${SQLPROXY}" = "1" ]; then
	export DBPORT=${RANDOM}
	export DOCKERIP=$(ip addr show dev docker0 | grep "inet " | awk '{print $2}' | awk -F/ '{print $1}')
	docker run -d --network="host" --name redir-mt-${DBPORT} fr3nd/redir redir --lport=${DBPORT} --laddr=${DOCKERIP} --cport=3306 --caddr=127.0.0.1 >/dev/null 2>&1

	ARGS="-e DB_SERVER=${DOCKERIP} -e DB_PORT=${DBPORT}"
fi;

docker run ${ARGS} -it -v $(pwd)/src:/moneytracker -h moneytracker.$(hostname -f) -p ${WEBPORT}:80 --rm moneytracker ${CMD}

if [ "${SQLPROXY}" = "1" ]; then
	docker kill "redir-mt-${DBPORT}" >/dev/null 2>&1
	docker rm "redir-mt-${DBPORT}" >/dev/null 2>&1
fi;
