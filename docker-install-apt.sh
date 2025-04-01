sudo apt-get update;
sudo apt-get install docker.io -y;
sudo systemctl start docker;
sudo docker --version;
sudo systemctl enable docker;
sudo usermod -a -G docker $(whoami);
newgrp docker;
