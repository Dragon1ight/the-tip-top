// Jobs déclarés dans le code : Jenkins se reconstruit à l'identique à chaque installation.
def repo = 'https://github.com/Dragon1ight/the-tip-top.git'

folder('tests') {
  description('Les sept jobs de test appelés par le Jenkinsfile.')
}

['lint', 'unit', 'integration', 'api', 'security', 'e2e-http', 'e2e-browser'].each { name ->
  pipelineJob("tests/${name}") {
    parameters { stringParam('COMMIT', 'dev', 'Commit à tester') }
    logRotator { numToKeep(30) }
    definition {
      cps {
        sandbox(true)
        script("""
pipeline {
  agent any
  stages {
    stage('${name}') {
      steps {
        checkout scmGit(branches: [[name: params.COMMIT]], userRemoteConfigs: [[url: '${repo}']])
        sh 'bash ci/tests/${name}.sh'
      }
    }
  }
  post {
    always {
      junit allowEmptyResults: true, testResults: 'reports/**/*.xml'
      archiveArtifacts allowEmptyArchive: true, artifacts: 'reports/**'
    }
  }
}
""")
      }
    }
  }
}

pipelineJob('rollback') {
  description('Remet le trafic sur la couleur précédente, sans reconstruction.')
  definition {
    cpsScm {
      scm {
        git {
          remote { url(repo) }
          branch('*/prod')
        }
      }
      scriptPath('ci/rollback.Jenkinsfile')
    }
  }
}

multibranchPipelineJob('the-tip-top') {
  branchSources {
    branchSource {
      source {
        github {
          id('the-tip-top')
          repoOwner('Dragon1ight')
          repository('the-tip-top')
          repositoryUrl(repo)
          configuredByUrl(false)
          traits {
            gitHubBranchDiscovery { strategyId(3) }
          }
        }
      }
    }
  }
  orphanedItemStrategy { discardOldItems { numToKeep(10) } }
}
