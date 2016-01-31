
# Install subsplit
git clone https://github.com/dflydev/git-subsplit.git
cd git-subsplit && sudo sh ./install.sh && cd ..

# Init the subplit
git subsplit init https://github.com/php-cache/cache.git
git subsplit update

FLAGS="--heads=master --no-tags"
# Push the changes to other repositories
git subsplit publish src/Cache/Adapter/Apc:git@github.com:php-cache/apc-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Apcu:git@github.com:php-cache/apcu-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Common:git@github.com:php-cache/common-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Doctrine:git@github.com:php-cache/doctrine-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Filesystem:git@github.com:php-cache/filesystem-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Memcache:git@github.com:php-cache/memcache-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Memcached:git@github.com:php-cache/memcached-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/MongoDB:git@github.com:php-cache/mongodb-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/PHPArray:git@github.com:php-cache/phparray-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Predis:git@github.com:php-cache/predis-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Redis:git@github.com:php-cache/redis-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/Void:git@github.com:php-cache/void-adapter.git $FLAGS
git subsplit publish src/Cache/Hierarchy:git@github.com:php-cache/hierarchical-cache.git $FLAGS
git subsplit publish src/Cache/IntegrationTests:git@github.com:php-cache/integration-tests.git $FLAGS
git subsplit publish src/Cache/SessionHandler:git@github.com:php-cache/session-handler.git $FLAGS
git subsplit publish src/Cache/Taggable:git@github.com:php-cache/taggable-cache.git $FLAGS

