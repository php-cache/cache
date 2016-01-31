
# Install subsplit
git clone https://github.com/dflydev/git-subsplit.git
cd git-subsplit && sudo sh ./install.sh && cd ..

# Init the subplit
git subsplit init https://github.com/subtree-test/main.git
git subsplit update

FLAGS="--heads=master --no-tags"
# Push the changes to other repositories
time git subsplit publish src/Cache/adapter-bar:git@github.com:subtree-test/adapter-bar.git $FLAGS
time git subsplit publish src/Cache/adapter-foo:git@github.com:subtree-test/adapter-foo.git $FLAGS
time git subsplit publish src/Cache/foobar-common:git@github.com:subtree-test/foobar-common.git $FLAGS
