FROM jenkins/jenkins:2.580.2-lts-jdk21
# Risque accepté : Jenkins pilote le démon Docker de l'hôte via son socket, ce qui équivaut à root sur l'hôte.
USER root
RUN apt-get update -qq && apt-get install -y -qq --no-install-recommends ca-certificates curl gnupg openssh-client >/dev/null \
 && install -m 0755 -d /etc/apt/keyrings \
 && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc \
 && echo "deb [signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release; echo $VERSION_CODENAME) stable" > /etc/apt/sources.list.d/docker.list \
 && apt-get update -qq && apt-get install -y -qq --no-install-recommends docker-ce-cli docker-compose-plugin docker-buildx-plugin >/dev/null \
 && rm -rf /var/lib/apt/lists/*
COPY plugins.txt /usr/share/jenkins/ref/plugins.txt
RUN jenkins-plugin-cli --plugin-file /usr/share/jenkins/ref/plugins.txt
COPY casc.yaml jobs.groovy /usr/share/jenkins/casc/
ENV CASC_JENKINS_CONFIG=/usr/share/jenkins/casc/casc.yaml JAVA_OPTS="-Djenkins.install.runSetupWizard=false -Xmx1g"
