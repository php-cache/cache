#!/usr/bin/env bash

set -euo pipefail

readonly container_name=php-cache-redis-cluster
readonly image=redis:7.2.15-bookworm

docker run --detach \
  --name "${container_name}" \
  --publish 0.0.0.0:7000:7000 \
  --publish 0.0.0.0:7001:7001 \
  --publish 0.0.0.0:7002:7002 \
  "${image}" \
  sh -ec '
    for port in 7000 7001 7002; do
      node_dir="/data/${port}"
      mkdir -p "${node_dir}"
      redis-server \
        --port "${port}" \
        --bind 0.0.0.0 \
        --protected-mode no \
        --cluster-enabled yes \
        --cluster-config-file nodes.conf \
        --cluster-node-timeout 5000 \
        --cluster-announce-ip 127.0.0.1 \
        --cluster-announce-port "${port}" \
        --cluster-announce-bus-port "$((port + 10000))" \
        --appendonly no \
        --save "" \
        --dir "${node_dir}" &
    done

    for port in 7000 7001 7002; do
      until redis-cli -p "${port}" ping >/dev/null 2>&1; do
        sleep 0.2
      done
    done

    redis-cli --cluster create \
      127.0.0.1:7000 \
      127.0.0.1:7001 \
      127.0.0.1:7002 \
      --cluster-replicas 0 \
      --cluster-yes

    wait
  '

for _ in {1..30}; do
  if cluster_info=$(docker exec "${container_name}" redis-cli -p 7000 cluster info); then
    if grep -q '^cluster_state:ok' <<< "${cluster_info}" && grep -q '^cluster_slots_assigned:16384' <<< "${cluster_info}"; then
      exit 0
    fi
  fi

  if [[ "$(docker inspect --format '{{.State.Running}}' "${container_name}" 2>/dev/null)" != true ]]; then
    break
  fi

  sleep 1
done

docker logs "${container_name}"
exit 1
