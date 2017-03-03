# MinnPost Donation Progress Widget

Uses the Salesforce Analytics API portion of the [Salesforce REST API](https://github.com/MinnPost/salesforce-rest-api/) plugin to show campaign progress from a Report.

This plugin creates a widget, which takes a Campaign ID and a Report ID. While its usefulness may be limited depending on all the different Report structures in Salesforce, for us it gets JSON data showing how much donation progress we've made as the Report shows, and renders it as a CSS3 thermometer.