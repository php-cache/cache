
# Adding phpcache as user, to use subsplit
openssl aes-256-cbc -K $encrypted_99f52e9ffe4d_key -iv $encrypted_99f52e9ffe4d_iv -in .travis/sshkey.enc -out .travis/sshkey -d
eval "$(ssh-agent -s)" #start the ssh agent
chmod 600 .travis/sshkey # this key should have push access
ssh-add .travis/sshkey # Add the ssh key to travis. This will give travis push access
