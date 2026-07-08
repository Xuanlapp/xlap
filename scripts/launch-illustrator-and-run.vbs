Option Explicit

Dim app
Dim scriptPath

scriptPath = WScript.Arguments.Item(0)

On Error Resume Next
Set app = GetObject(, "Illustrator.Application")
If Err.Number <> 0 Then
    Err.Clear
    Set app = CreateObject("Illustrator.Application")
End If

If Err.Number <> 0 Or app Is Nothing Then
    WScript.Echo "Cannot start Adobe Illustrator."
    WScript.Quit 1
End If
On Error GoTo 0

WScript.Sleep 1500

On Error Resume Next
app.DoJavaScriptFile scriptPath
If Err.Number <> 0 Then
    WScript.Echo "Failed to run JSX: " & Err.Description
    WScript.Quit 1
End If
On Error GoTo 0