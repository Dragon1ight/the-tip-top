// Pipeline multibranch : dev -> DEV, preprod -> PREPROD, prod -> blue/green avec approbation humaine.
def TEST_JOBS = ['lint', 'unit', 'integration', 'api', 'security', 'e2e-http', 'e2e-browser']

pipeline {
    agent any
    options {
        disableConcurrentBuilds()
        timestamps()
        buildDiscarder(logRotator(numToKeepStr: '30', artifactNumToKeepStr: '30'))
        // Plus long que le délai laissé à l'approbation humaine (24 h).
        timeout(time: 26, unit: 'HOURS')
    }
    environment {
        REGISTRY = "${env.REGISTRY_HOST ?: 'localhost:5000'}"
        IMAGE = "${REGISTRY}/the-tip-top"
    }
    stages {
        stage('Anti-boucle') {
            steps {
                script {
                    def author = sh(script: 'git log -1 --format=%an', returnStdout: true).trim()
                    def message = sh(script: 'git log -1 --format=%B', returnStdout: true).trim()
                    env.SKIP_BUILD = (author == 'jenkins-bot' || message.contains('[ci skip]')) ? 'true' : 'false'
                    env.SHORT_COMMIT = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
                    if (env.SKIP_BUILD == 'true') {
                        echo 'Commit produit par Jenkins : pipeline ignoré.'
                        currentBuild.result = 'NOT_BUILT'
                    }
                }
            }
        }
        stage('Pipeline') {
            when { expression { env.SKIP_BUILD != 'true' } }
            stages {
                stage('Tests') {
                    steps {
                        script {
                            for (job in TEST_JOBS) {
                                stage("Job ${job}") {
                                    build job: "/tests/${job}", parameters: [string(name: 'COMMIT', value: env.GIT_COMMIT)]
                                }
                            }
                        }
                    }
                }
                stage('Build image') {
                    steps { sh 'docker build -t "$IMAGE:$SHORT_COMMIT" .' }
                }
                stage('Scan image') {
                    steps { sh 'bash ci/scan-image.sh "$IMAGE:$SHORT_COMMIT"' }
                }
                stage('Push Registry') {
                    steps { sh 'docker push "$IMAGE:$SHORT_COMMIT"' }
                }
                stage('Déploiement DEV') {
                    when { branch 'dev' }
                    steps { sh 'bash ci/deploy-env.sh dev "$IMAGE:$SHORT_COMMIT"' }
                }
                stage('Charge et SEO') {
                    when { branch 'dev' }
                    steps {
                        sh 'COMMIT="$SHORT_COMMIT" bash ci/tests/load-seo.sh'
                    }
                    post { always { archiveArtifacts allowEmptyArchive: true, artifacts: 'reports/**' } }
                }
                stage('Déploiement PREPROD') {
                    when { branch 'preprod' }
                    steps { sh 'bash ci/deploy-env.sh preprod "$IMAGE:$SHORT_COMMIT"' }
                }
                stage('Production blue/green') {
                    when { branch 'prod' }
                    stages {
                        stage('Couleur inactive') {
                            steps {
                                script {
                                    def active = readFile('active_color.txt').trim()
                                    env.ACTIVE_COLOR = active
                                    env.TARGET_COLOR = active == 'blue' ? 'green' : 'blue'
                                }
                                withCredentials([string(credentialsId: 'render-api-key', variable: 'RENDER_API_KEY')]) {
                                    sh 'bash ci/render-deploy.sh "$TARGET_COLOR" "$GIT_COMMIT"'
                                }
                                sh 'bash ci/smoke.sh "$TARGET_COLOR"'
                            }
                        }
                        stage('Approbation') {
                            steps {
                                timeout(time: 24, unit: 'HOURS') {
                                    input message: "Basculer la production de ${env.ACTIVE_COLOR} vers ${env.TARGET_COLOR} (commit ${env.SHORT_COMMIT}) ?", ok: 'Basculer'
                                }
                            }
                        }
                        stage('Bascule') {
                            steps {
                                sh 'bash ci/switch-color.sh "$TARGET_COLOR"'
                                withCredentials([usernamePassword(credentialsId: 'github-bot', usernameVariable: 'GIT_USER', passwordVariable: 'GIT_TOKEN')]) {
                                    sh 'bash ci/commit-color.sh "$TARGET_COLOR"'
                                }
                            }
                        }
                        stage('Vérification') {
                            steps {
                                script {
                                    def healthy = sh(script: 'bash ci/smoke.sh public', returnStatus: true) == 0
                                    if (!healthy) {
                                        // Un déploiement raté compte dans le taux d'échec DORA.
                                        sh 'bash ci/dora.sh deploy'
                                        sh 'bash ci/switch-color.sh "$ACTIVE_COLOR"'
                                        withCredentials([usernamePassword(credentialsId: 'github-bot', usernameVariable: 'GIT_USER', passwordVariable: 'GIT_TOKEN')]) {
                                            sh 'bash ci/commit-color.sh "$ACTIVE_COLOR"'
                                        }
                                        sh 'bash ci/dora.sh rollback'
                                        error 'Production en échec : retour automatique sur l\'ancienne couleur.'
                                    }
                                }
                                sh 'bash ci/dora.sh deploy'
                            }
                        }
                    }
                }
            }
        }
    }
    post {
        failure { echo "Pipeline en échec sur ${env.BRANCH_NAME} (commit ${env.SHORT_COMMIT})." }
    }
}
