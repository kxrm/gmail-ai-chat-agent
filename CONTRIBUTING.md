# Contributing to Gmail AI Chat Agent

Thank you for your interest in contributing to the Gmail AI Chat Agent! This guide will help you understand our development workflow and contribution standards.

## 🚦 **Branch Protection Policy**

**IMPORTANT**: Direct pushes to `master` are **disabled**. All contributions must follow this workflow:

1. **Create feature branch** from latest master
2. **Make changes** and commit with descriptive messages
3. **Push branch** and create Pull Request
4. **Wait for CI** to pass (all 4 status checks required)
5. **Get approval** from at least 1 reviewer
6. **Merge** via GitHub UI (squash or merge commit)

---

## 🚀 **Quick Start**

### **Prerequisites**
- Docker & VS Code with Remote-Containers extension **OR**
- PHP 8.2+, Composer, and dependencies manually installed

### **Development Setup**

#### **Option 1: DevContainer (Recommended)**
```bash
# Clone the repository
git clone https://github.com/kxrm/gmail-ai-chat-agent.git
cd gmail-ai-chat-agent

# Open in VS Code with DevContainer
code .
# VS Code will prompt to "Reopen in Container" - click Yes
# Container auto-builds with all dependencies
```

#### **Option 2: Local Development**
```bash
# Clone and install dependencies
git clone https://github.com/kxrm/gmail-ai-chat-agent.git
cd gmail-ai-chat-agent
composer install

# Create storage directories
mkdir -p storage/logs storage/sessions storage/sessions/temp_sessions
chmod 755 storage/logs storage/sessions storage/sessions/temp_sessions

# Copy OAuth template
cp config/client_secret.json.example config/client_secret.json
# Edit with your Google OAuth credentials
```

---

## 🔄 **Development Workflow**

### **1. Start New Feature/Fix**
```bash
# Sync with latest master
git checkout master
git pull origin master

# Create feature branch
git checkout -b feature/your-feature-name
# OR for bug fixes:
git checkout -b fix/bug-description
# OR for docs:
git checkout -b docs/what-you-are-documenting
```

### **2. Make Changes**
- Write code following our standards (see Code Quality section)
- Add tests for new functionality
- Update documentation if needed

### **3. Test Locally**
```bash
# Run full test suite
composer test

# Check code style
composer cs:check

# Run static analysis  
composer stan

# Check test coverage
composer test:cov
```

### **4. Commit Changes**
```bash
# Stage changes
git add .

# Commit with descriptive message
git commit -m "feat: add email thread summarization feature

- Implement ThreadSummaryService for multi-email analysis
- Add caching for improved performance  
- Include unit tests with 95% coverage
- Update README with new feature documentation

Fixes #123"
```

### **5. Create Pull Request**
```bash
# Push feature branch
git push origin feature/your-feature-name

# Go to GitHub and create PR
# Fill out the PR template completely
# Link to any related issues
```

### **6. Address Review Feedback**
```bash
# Make requested changes
git add .
git commit -m "fix: address review feedback - improve error handling"
git push origin feature/your-feature-name
# PR automatically updates
```

---

## 🧪 **Testing Requirements**

### **Required Tests**
- ✅ **Unit Tests**: All new classes/methods must have unit tests
- ✅ **Integration Tests**: For component interactions
- ✅ **E2E Tests**: For user-facing features (OAuth, email processing)

### **Coverage Standards**
- **Minimum**: 90% line coverage (enforced by CI)
- **Target**: 95%+ for new code
- **Critical paths**: 100% coverage (OAuth, email processing, AI integration)

### **Running Tests**
```bash
# All tests
composer test

# Specific test types
vendor/bin/phpunit tests/unit/
vendor/bin/phpunit tests/integration/
vendor/bin/phpunit tests/e2e/

# With coverage
composer test:cov

# Mutation testing (for code quality)
composer test:mut
```

---

## 🎯 **Code Quality Standards**

### **Coding Standards**
- **PSR-12** compliance (checked by `composer cs:check`)
- **PHPStan Level 6+** static analysis (checked by `composer stan`)
- **Descriptive naming** for classes, methods, and variables
- **Comprehensive docblocks** for public methods

### **Architecture Principles**
- **Single Responsibility**: Each class has one clear purpose
- **Dependency Injection**: Use constructor injection
- **Interface Segregation**: Small, focused interfaces
- **Session Abstraction**: Use ArraySession for testing, PhpSession for production

### **Security Requirements**
- **No secrets in code**: Use environment variables or config templates
- **Input validation**: Sanitize all user inputs
- **OAuth security**: Follow Google's OAuth 2.0 best practices
- **Error handling**: Don't expose sensitive information in errors

---

## 🔐 **Security Guidelines**

### **OAuth Configuration**
```bash
# NEVER commit real credentials
config/client_secret.json          # ← This file is .gitignored

# ALWAYS use the template
config/client_secret.json.example  # ← Template for contributors
```

### **Environment Variables**
```bash
# For sensitive configuration, use environment variables
GOOGLE_CLIENT_ID=your-client-id
GOOGLE_CLIENT_SECRET=your-client-secret
OLLAMA_HOST=http://localhost:11434
```

### **What NOT to Commit**
- ❌ Real OAuth credentials
- ❌ Session files (storage/sessions/sess_*)
- ❌ Log files (storage/logs/*.log)
- ❌ User email data
- ❌ AI model responses with personal information

---

## 🔍 **Pull Request Process**

### **PR Requirements**
Your PR will be automatically checked for:

1. **CI Status Checks** (all must pass):
   - ✅ Test Suite (PHP 8.2)
   - ✅ Test Suite (PHP 8.3) 
   - ✅ Security Scan
   - ✅ Code Quality Analysis

2. **Review Requirements**:
   - ✅ At least 1 approval from maintainer
   - ✅ All review comments addressed
   - ✅ PR template completed

3. **Content Requirements**:
   - ✅ Tests added for new functionality
   - ✅ Documentation updated if needed
   - ✅ No merge conflicts with master
   - ✅ Branch up-to-date with master

### **PR Template Checklist**
When creating a PR, you'll see a template with:
- [ ] Code quality checks passed
- [ ] Tests added and passing
- [ ] Security requirements met
- [ ] Documentation updated
- [ ] No breaking changes (or migration guide provided)

---

## 🏷️ **Issue Guidelines**

### **Bug Reports**
Use the bug report template and include:
- **Environment**: PHP version, OS, DevContainer vs local
- **Steps to reproduce**: Exact steps that cause the issue
- **Expected behavior**: What should happen
- **Actual behavior**: What actually happens
- **Logs**: Relevant error messages or logs

### **Feature Requests**
Use the feature request template and include:
- **Problem**: What problem does this solve?
- **Solution**: Describe your proposed solution
- **Alternatives**: Other solutions you considered
- **Impact**: Who would benefit from this feature?

---

## 🚀 **Release Process**

### **Version Strategy**
- **Semantic Versioning**: MAJOR.MINOR.PATCH (e.g., v1.2.3)
- **Breaking Changes**: Require major version bump
- **New Features**: Minor version bump
- **Bug Fixes**: Patch version bump

### **Release Workflow** (Maintainers Only)
```bash
# Create release tag
git tag v1.2.3
git push origin v1.2.3

# GitHub Actions automatically:
# 1. Runs full validation
# 2. Creates GitHub release
# 3. Generates changelog
# 4. Updates project version
```

---

## 🛠️ **Development Tips**

### **DevContainer Benefits**
- **Consistent Environment**: Same PHP version, extensions, tools
- **Pre-configured**: OAuth templates, storage directories, permissions
- **VS Code Integration**: Debugging, testing, Git integration
- **Isolated**: Won't conflict with other PHP projects

### **Debugging**
```bash
# Enable Xdebug in DevContainer
# Set breakpoints in VS Code
# Run tests with debugging enabled

# Check logs
tail -f storage/logs/chat_app.log

# Test OAuth flow
# Use ngrok or similar for local HTTPS callback
```

### **Performance Testing**
```bash
# Profile email processing
composer test tests/e2e/EmailProcessingPerformanceTest.php

# Check memory usage
composer test tests/integration/MemoryUsageTest.php
```

---

## 📊 **Project Health Metrics**

We maintain these standards:
- **Test Coverage**: 90%+ line coverage
- **Code Quality**: PSR-12 compliance, PHPStan Level 6
- **Security**: No known vulnerabilities
- **Performance**: <2s response time for email processing
- **Dependencies**: Weekly security updates

---

## 💬 **Getting Help**

### **Documentation**
- **README.md**: Project overview and setup
- **.github/workflows/README.md**: CI/CD pipeline documentation
- **Code comments**: Inline documentation for complex logic

### **Support Channels**
- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: Questions and community support
- **Code Review**: Learn from PR feedback

### **Maintainer Response Times**
- **Critical Security Issues**: Within 24 hours
- **Bug Reports**: Within 3 business days
- **Feature Requests**: Within 1 week
- **PR Reviews**: Within 2 business days

---

## 🎉 **Recognition**

Contributors are recognized in:
- **CHANGELOG.md**: Major contributions noted in release notes
- **GitHub Contributors**: Automatic recognition on repository
- **Release Notes**: Significant features attributed to contributors

---

**Thank you for contributing to Gmail AI Chat Agent!** 🚀

Your contributions help make email management more intelligent and efficient for everyone.
