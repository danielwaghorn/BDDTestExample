Feature: User can navigate website
	As a user
	I need to be able to navigate between pages of the website
	So that I can find what I need

	Scenario: Home page shows navigation
		Given I am on the home page
		When I do nothing
		Then I should see "Home Page"

	Scenario: Can navigate to products
		Given I am on the home page
		When I click "Products"
		Then I should see "Product Page"