// Rollback à la demande : remet le trafic sur l'autre couleur, sans reconstruction.
pipeline {
    agent any
    stages {
        stage('Rollback') {
            steps {
                script {
                    def active = readFile('active_color.txt').trim()
                    env.BACK = active == 'blue' ? 'green' : 'blue'
                }
                input message: "Remettre le trafic sur ${env.BACK} ?", ok: 'Rollback'
                sh 'bash ci/smoke.sh "$BACK"'
                sh 'bash ci/switch-color.sh "$BACK"'
                withCredentials([usernamePassword(credentialsId: 'github-bot', usernameVariable: 'GIT_USER', passwordVariable: 'GIT_TOKEN')]) {
                    sh 'bash ci/commit-color.sh "$BACK"'
                }
                sh 'bash ci/dora.sh rollback'
            }
        }
    }
}
