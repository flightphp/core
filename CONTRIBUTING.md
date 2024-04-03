## Contributing to the Flight Framework

Thanks for being willing to contribute to the Flight! The goal of Flight is to keep the implementation of things simple and free of outside dependencies. 
You should only bring in the depedencies you want in your project right? Right.

### Overarching Guidelines

Flight aims to be simple and fast. Anything that compromises either of those two things will be heavily scrutinized and/or rejected. Other things to consider when making a contribution:

* **Dependencies** - We strive to be dependency free in Flight. Yes even polyfills, yes even `Interface` only repos like `psr/container`. The fewer dependencies, the fewer your exposed attack vectors.

* **Coding Standards** - We use PSR1 coding standards enforced by PHPCS. Some standards that either need additional configuration or need to be manually done are:
  *  PHPStan is at level 6.
  *  `===` instead of truthy or falsey statements like `==` or `!is_array()`.
 
* **PHP 7.4 Focused** - We do not make PHP 8+ focused enhancements on the framework as the focus is maintaining PHP 7.4.

* **Core functionality vs Plugin** - Have a conversation with us in the [chatroom](https://matrix.to/#/!cTfwPXhpkTXPXwVmxY:matrix.org?via=matrix.org&via=leitstelle511.net&via=integrations.ems.host) to know if your idea is worth makes sense in the framework or in a plugin.

* **Testing** - Until automated testing is put into place, any PRs must pass unit testing in PHP 7.4 and PHP 8.2+. Additionally you need to run `composer test-server` and `composer test-server-v2` and ensure all the header links work correctly.

#### **Did you find a bug?**

* **Do not open up a GitHub issue if the bug is a security vulnerability**. Instead contact maintainers directly via email to safely pass in the information related to the security vuln.

* **Ensure the bug was not already reported** by searching on GitHub under [Issues](https://github.com/flightphp/core/issues).

* If you're unable to find an open issue addressing the problem, [open a new one](https://github.com/flightphp/core/issues/new). Be sure to include a **title and clear description**, as much relevant information as possible, and a **code sample** or an **executable test case** demonstrating the expected behavior that is not occurring.

#### **Did you write a patch that fixes a bug?**

* Open a new GitHub pull request with the patch.

* Ensure the PR description clearly describes the problem and solution. Include the relevant issue number if applicable.

#### **Did you fix whitespace, format code, or make a purely cosmetic patch?**

Changes that are cosmetic in nature and do not add anything substantial to the stability, functionality, or testability of Flight will generally not be accepted.

#### **Do you intend to add a new feature or change an existing one?**

* Hop into the [chatroom](https://matrix.to/#/!cTfwPXhpkTXPXwVmxY:matrix.org?via=matrix.org&via=leitstelle511.net&via=integrations.ems.host) for Flight and let's have a conversation about the feature you want to add. It could be amazing, or it might make more sense as an extension/plugin. If you create a PR without having a conversation with maintainers, it likely will be closed without review.

* Do not open an issue on GitHub until you have collected positive feedback about the change. GitHub issues are primarily intended for bug reports and fixes.

#### **Do you have questions about the source code?**

* Ask any question about how to use Flight in the in the [Flight Matrix chat room](https://matrix.to/#/!cTfwPXhpkTXPXwVmxY:matrix.org?via=matrix.org&via=leitstelle511.net&via=integrations.ems.host).

#### **Do you want to contribute to the Flight documentation?**

* Please see the [Flight Documentation repo on GitHub](https://github.com/flightphp/docs).

Flight is a volunteer effort. We encourage you to pitch in and join!

Thanks! :heart: :heart: :heart:

Flight Team
