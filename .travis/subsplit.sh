
# Install subsplit
git clone https://github.com/dflydev/git-subsplit.git
cd git-subsplit && sudo sh ./install.sh && cd ..

# Init the subplit
git subsplit init https://github.com/php-cache/cache.git
git subsplit update

FLAGS="--heads=master --no-tags"
# Push the changes to other repositories
git subsplit publish src/Cache/Adapter/Filesystem:git@github.com:php-cache/filesystem-adapter.git $FLAGS
git subsplit publish src/Cache/Adapter/MongoDB:git@github.com:php-cache/mongodb-adapter.git $FLAGS
git subsplit publish src/Cache/SessionHandler:git@github.com:php-cache/session-handler.git $FLAGS

