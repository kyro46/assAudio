# assAudio
Audiorecorder-Questiontypeplugin for ILIAS 6 (for 5.2.x to 5.4.x see branches and releases)

### Questiontype to record audio without a Java-Applet or Adobe Flash ###

The questiontype records media with built-in methods of the browser.

This plugin will add a questiontype, that:
* Records the voice of the participant
* Allows pause and resume while the recording is not finished
* Indicates successfull microphone input with a waveform
* Lets the participant rehear the recording
* Supports autosave and forced submission even in case the recording was not properly finished within an interval (see below)

Important for admins:
* The recordings are stored as *recording\_[active-fi]\_[#testpass]\_[timestamp].webm* in the respective subfolder for assessment in the data directory
* The whole history of recordings is kept to allow the retrival of an earlier submission in case of an error in the browser or in ILIAS 
* The size of a single recording will be around **700kB/min** - keep in mind that a separate recording is created per autosave/edited submission
* The recordings will be deleted when the participants data or the test itself are deleted
* The interval in which the input is automatically prepared for submission increases over time to ease the CPU-load for the client
  * First minute: every 5 seconds
  * Minute 1-5: every 10 seconds
  * After 5 minutes: every 20 seconds

### Usage ###

Install the plugin

```bash
mkdir -p Customizing/global/plugins/Modules/TestQuestionPool/Questions  
cd Customizing/global/plugins/Modules/TestQuestionPool/Questions
git clone https://github.com/kyro46/assAudio.git
```
and activate it in the ILIAS-Admin-GUI. Activate manual correction.

### Known Problems ###

* Audio capture not yet possible with Safe Exam Browser 3.x

* The plugin uses a flash-free approach which is not yet compatible with all browsers as the technology is currently work-in-progress at W3C. See:
  * https://www.w3.org/TR/mediastream-recording/
  * https://caniuse.com/#feat=mediarecorder
  
* Textual feedback about the length of a recording is only provided in Firefox

### Credits ###
* Development by Christoph Jobst
