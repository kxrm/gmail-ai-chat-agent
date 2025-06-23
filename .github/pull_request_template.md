## 📋 Pull Request Checklist

Thank you for contributing to Gmail AI Chat Agent! Please ensure your PR meets these requirements:

### 🔍 **Code Quality**
- [ ] Code follows PSR-12 standards (`composer cs:check` passes)
- [ ] Static analysis passes (`composer stan` passes)  
- [ ] No new PHPStan errors introduced
- [ ] Code is well-commented, especially complex logic

### 🧪 **Testing**
- [ ] All existing tests pass (`composer test`)
- [ ] New tests added for new functionality
- [ ] Test coverage maintained at 90%+ (`composer test:cov`)
- [ ] Integration tests updated if component interactions changed
- [ ] E2E tests added for user-facing features

### 🔐 **Security** 
- [ ] No sensitive data (API keys, passwords, email content) in code
- [ ] OAuth configuration uses template files only (`config/client_secret.json.example`)
- [ ] New dependencies are security-audited
- [ ] No secrets detected by automated scanning
- [ ] Input validation added for user-facing features

### 📚 **Documentation**
- [ ] README updated if public API changes
- [ ] Changelog entry added for user-facing changes
- [ ] Code comments explain complex business logic
- [ ] Docblocks updated for new public methods

### 🔄 **Workflow**
- [ ] Branch is up-to-date with master (`git pull origin master`)
- [ ] Commit messages are descriptive and follow conventional format
- [ ] No merge commits (rebased if needed)
- [ ] All CI/CD Pipeline checks pass

---

## �� **Description**

### What does this PR do?
<!-- Describe the changes in this PR -->

### Why is this change needed?
<!-- Link to issue number or explain the motivation -->

### How was this tested?
<!-- Describe your testing approach beyond automated tests -->

### Screenshots/Logs (if applicable)
<!-- Add screenshots or logs that demonstrate the change -->

---

## 🏷️ **Type of Change**
<!-- Mark ONE primary type -->
- [ ] 🐛 **Bug fix** (non-breaking change that fixes an issue)
- [ ] ✨ **New feature** (non-breaking change that adds functionality)
- [ ] 💥 **Breaking change** (fix or feature that causes existing functionality to change)
- [ ] 📚 **Documentation** (updates to docs, comments, or README)
- [ ] 🔧 **Maintenance** (dependency updates, refactoring, tooling)
- [ ] 🔐 **Security** (security improvements or vulnerability fixes)

---

## ⚠️ **Breaking Changes** 
<!-- Complete this section if you marked "Breaking change" above -->
- [ ] No breaking changes in this PR

**If breaking changes exist:**
- What breaks: <!-- Describe what existing functionality changes -->
- Migration steps: <!-- How should users update their code/config -->
- Deprecation period: <!-- How long before old API is removed -->

---

## 🔗 **Related Issues**
<!-- Link to GitHub issues this PR addresses -->
- Fixes #<!-- issue number -->
- Related to #<!-- issue number -->
- Part of #<!-- issue number -->

---

## 🧪 **Testing Evidence**

### **Automated Tests**
```bash
# Include output of key test runs
$ composer test
# Paste relevant test output

$ composer cs:check  
# Paste style check output

$ composer stan
# Paste static analysis output
```

### **Manual Testing**
<!-- Describe manual testing performed -->
- [ ] Tested OAuth flow end-to-end
- [ ] Verified email processing with real Gmail data
- [ ] Tested AI chat functionality with Ollama
- [ ] Verified DevContainer setup works
- [ ] Tested with multiple PHP versions (8.2, 8.3)

---

## 📊 **Impact Assessment**

### **Performance Impact** 
- [ ] No performance impact
- [ ] Improves performance 
- [ ] May impact performance (explain below)

### **Security Impact**
- [ ] No security impact
- [ ] Improves security
- [ ] Changes security model (explain below)

### **User Experience Impact**  
- [ ] No UX changes
- [ ] Improves UX
- [ ] Changes UX (explain below)

---

## ✅ **Final Checks**
- [ ] I have tested this change locally in DevContainer
- [ ] I have tested this change with real Gmail/OAuth data
- [ ] I have reviewed my own code for quality and security
- [ ] I have verified all CI checks pass
- [ ] This PR is ready for maintainer review

---

## 👀 **Reviewer Notes**
<!-- Any specific areas you'd like reviewers to focus on -->

**Focus areas for review:**
- [ ] Security implications of OAuth handling
- [ ] Performance of email processing logic  
- [ ] Test coverage of edge cases
- [ ] Documentation clarity
- [ ] Architecture and design patterns

**Questions for reviewers:**
<!-- Any specific questions or concerns -->

---

/cc @kxrm <!-- Notify maintainer -->
