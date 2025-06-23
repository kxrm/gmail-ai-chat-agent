# GitHub Actions Workflows

This directory contains all GitHub Actions workflows that automate project maintenance, testing, and releases. Each workflow has a specific purpose to ensure project health and maintainability.

## 🔄 **Core Workflows** (Run on every change)

### **CI/CD Pipeline** (`ci.yml`)
**Purpose**: Comprehensive testing and validation pipeline  
**Triggers**: Push to `master`/`develop`, Pull Requests, Daily schedule (2 AM UTC)  
**What it does**:
- ✅ **Test Suite**: Matrix testing across PHP 8.2 & 8.3
- ✅ **Coverage**: Generates coverage reports (PHP 8.2 only) 
- ✅ **Security Scan**: Dependency audit + secret pattern detection
- ✅ **Code Quality**: PSR-12 style, PHPStan analysis, mutation testing

**Key Features**:
- Non-blocking quality checks (won't fail on style issues)
- Uploads coverage to Codecov
- Creates coverage artifacts for download
- Validates sensitive files aren't committed

---

## 🔧 **Maintenance Workflows** (Automated upkeep)

### **Dependency Updates** (`dependency-updates.yml`)
**Purpose**: Automated dependency management  
**Triggers**: Weekly (Mondays 9 AM UTC), Manual dispatch  
**What it does**:
- Updates Composer dependencies while respecting constraints
- Runs full test suite with updated dependencies
- Creates PRs with change summaries and test results
- Includes security audit of updated packages

### **Issue & PR Management** (`issue-management.yml`)
**Purpose**: GitHub repository automation  
**Triggers**: Issue/PR events, Weekly cleanup (Sundays 12 PM UTC)  
**What it does**:
- **Auto-labeling**: Bugs, features, docs, security issues
- **PR labeling**: Based on changed files and size
- **Stale management**: Marks inactive issues/PRs stale after 30 days
- **New contributor welcome**: Welcomes first-time contributors

### **Container Security** (`container-security.yml`)
**Purpose**: DevContainer and Docker security scanning  
**Triggers**: Changes to `.devcontainer/` or `Dockerfile`, Weekly (Mondays 6 AM UTC)  
**What it does**:
- Scans DevContainer for vulnerabilities (Trivy + Grype)
- Lints Dockerfile with Hadolint
- Checks for base image updates
- Uploads security reports to GitHub Security tab

---

## 🚀 **Release Workflows** (Version management)

### **Release Management** (`release.yml`)
**Purpose**: Automated release creation and management  
**Triggers**: Git tags (`v*`), Manual dispatch with version input  
**What it does**:
- Validates release with full test suite
- Generates changelog from git history
- Creates GitHub releases with artifacts
- Updates project version in `composer.json`
- Includes project statistics and installation instructions

---

## 📊 **Workflow Status & Dependencies**

### **Dependencies Between Workflows**
- **CI/CD Pipeline**: Independent, runs first
- **Dependency Updates**: Depends on CI passing for PRs
- **Release Management**: Requires all tests to pass before release
- **Other workflows**: Independent maintenance automation

### **Coverage Strategy**
- **Unit Tests**: 124 tests across core functionality
- **Integration Tests**: 8 tests for component interaction  
- **E2E Tests**: 10 tests for end-to-end scenarios
- **Target Coverage**: 90%+ line coverage (currently achieved)

### **Security Strategy**
- **Dependency Scanning**: Weekly + on every change
- **Secret Detection**: Pattern-based scanning on every commit
- **Container Security**: Weekly vulnerability scans
- **Manual Reviews**: Required for all PRs

---

## 🛠️ **For Maintainers**

### **Adding New Workflows**
1. Create workflow in `.github/workflows/`
2. Add clear name and description comments
3. Update this README with purpose and triggers
4. Test with `workflow_dispatch` before committing

### **Debugging Failed Workflows**
1. Check the **Actions** tab for detailed logs
2. **CI/CD Pipeline** failures usually indicate code issues
3. **Dependency Updates** may fail on breaking changes
4. **Security scans** may fail on new vulnerabilities

### **Modifying Existing Workflows**
1. Always test changes on a feature branch first
2. Use `workflow_dispatch` triggers for manual testing
3. Check for action version updates quarterly
4. Update this documentation when changing workflow purposes

### **Monitoring & Alerts**
- **Daily tests** catch dependency drift
- **Weekly scans** catch security issues
- **Stale management** keeps issues manageable
- **Failed workflows** should be investigated promptly

---

## 📈 **Project Health Metrics**

These workflows maintain the following project health standards:
- **✅ Test Coverage**: 90%+ line coverage
- **✅ Code Quality**: PSR-12 compliance, PHPStan level 6
- **✅ Security**: No known vulnerabilities in dependencies
- **✅ Dependencies**: Up-to-date within semantic version constraints
- **✅ Documentation**: Auto-updated changelogs and release notes
- **✅ Container Security**: Regular vulnerability scanning

---

*Last updated: When workflows change, update this README to reflect current automation strategy.* 