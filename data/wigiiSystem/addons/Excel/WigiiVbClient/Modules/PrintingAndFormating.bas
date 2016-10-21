Attribute VB_Name = "PrintingAndFormating"
'**
'*  This file is part of Wigii.
'*  Wigii is developed to inspire humanity. To Humankind we offer Gracefulness, Righteousness and Goodness.
'*
'*  Wigii is free software: you can redistribute it and/or modify it
'*  under the terms of the GNU General Public License as published by
'*  the Free Software Foundation, either version 3 of the License,
'*  or (at your option) any later version.
'*
'*  Wigii is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
'*  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
'*  See the GNU General Public License for more details.
'*
'*  A copy of the GNU General Public License is available in the Readme folder of the source code.
'*  If not, see <http://www.gnu.org/licenses/>.
'*
'*  @copyright  Copyright (c) 2016  Wigii.org
'*  @author     <http://www.wigii.org/system>      Wigii.org
'*  @link       <http://www.wigii-system.net>      <https://github.com/wigii/wigii>   Source Code
'*  @license    <http://www.gnu.org/licenses/>     GNU General Public License
'*/

'---------------------------------------------------------------
'- PRINTING & FORMATING
'---------------------------------------------------------------
'- Author  : Camille WEBER
'- Update  : Thursday, May 7th 2009 / 07h30
'- Version : 1.1
'---------------------------------------------------------------
'- This package exports methods to format and print tables
'---------------------------------------------------------------

'- Public methods :

'-----------------------------------------------------------------
'- TABLE_FORMAT
'-----------------------------------------------------------------
'- Purpose : Formats a table :
'-           puts a border around it,
'-           centers (or not) the data,
'-           puts horizontal inside borders (or not),
'-           defines the header and the data inside color
'-           (see ColorIndex in help)
'- Input   : the table, the options
'- Output  : ! The sheet containing the table must be active and
'-           ! unprotected.
'-----------------------------------------------------------------
Public Sub Table_Format(table As LogTable, _
                        Optional CenterData As Boolean = False, _
                        Optional HorizontalInsideBorders As Boolean = False, _
                        Optional HeaderColor As Variant = xlNone, _
                        Optional DataColor As Variant = xlNone)

    'Formats the header
    If table.HasHeader Then
        Range_Format table.header, True, False, HeaderColor
    End If
    'Formats the data
    If Not table.IsEmpty Then
        Range_Format table.data, CenterData, HorizontalInsideBorders, DataColor
    End If
End Sub

'-----------------------------------------------------------------
'- RANGE_FORMAT
'-----------------------------------------------------------------
'- Purpose : Formats a range :
'-           puts a border around it,
'-           centers (or not) the data,
'-           puts horizontal inside borders (or not),
'-           defines inside color (see ColorIndex in help)
'- Input   : the range, the options
'- Output  : ! The sheet containing the range must be active and
'-           ! unprotected.
'-----------------------------------------------------------------
Public Sub Range_Format(R As Range, _
                        Optional CenterData As Boolean = False, _
                        Optional HorizontalInsideBorders As Boolean = False, _
                        Optional InsideColor As Variant = xlNone)

    Dim ac As Range
    Set ac = ActiveCell
    R.Select
    
    'Borders
    With R.Borders(xlEdgeLeft)
        .LineStyle = xlContinuous
        .Weight = xlThin
        .ColorIndex = xlAutomatic
    End With
    With R.Borders(xlEdgeTop)
        .LineStyle = xlContinuous
        .Weight = xlThin
        .ColorIndex = xlAutomatic
    End With
    With R.Borders(xlEdgeBottom)
        .LineStyle = xlContinuous
        .Weight = xlThin
        .ColorIndex = xlAutomatic
    End With
    With R.Borders(xlEdgeRight)
        .LineStyle = xlContinuous
        .Weight = xlThin
        .ColorIndex = xlAutomatic
    End With
    If R.Columns.count > 1 Then
        With R.Borders(xlInsideVertical)
            .LineStyle = xlContinuous
            .Weight = xlThin
            .ColorIndex = xlAutomatic
        End With
    End If
    If (R.rows.count > 1) Then
        If HorizontalInsideBorders Then
            With R.Borders(xlInsideHorizontal)
                .LineStyle = xlContinuous
                .Weight = xlThin
                .ColorIndex = xlAutomatic
            End With
        Else
            R.Borders(xlInsideHorizontal).LineStyle = xlNone
        End If
    End If
    'Alignement
    With R
        If CenterData Then
            .HorizontalAlignment = xlCenter
        Else
            .HorizontalAlignment = xlGeneral
        End If
        .VerticalAlignment = xlBottom
        .WrapText = False
        .Orientation = 0
        .ShrinkToFit = False
        .MergeCells = False
    End With
    'Inside color
    R.Interior.ColorIndex = InsideColor
    
    ac.Select
End Sub

'-----------------------------------------------------------------
'- RANGE_REGFORMAT
'-----------------------------------------------------------------
'- Purpose : Formats a range according to escape sequences :
'-           $g...$g : puts the content in bold
'-           $i...$i : puts the content in italic
'-           $s...$s : underlines the content
'-           $$      : prints $
'- Input   : the range
'- Output  : -
'-----------------------------------------------------------------
Public Sub Range_RegFormat(R As Range)
  Dim bold As Integer, italic As Integer, underlined As Integer
  Dim n As Integer, ni As Integer, ni1 As Integer, ni2 As Integer, i As Integer
  Dim code As String, val As String
  
  If VarType(R.Cells(1, 1)) <> vbString Then
    Exit Sub
  End If
  
  n = R.Cells(1, 1).Characters.count: i = 1
  val = R.Cells(1, 1).Value2: ni = 0
  ni1 = InStr(1, val, "$g")
  ni2 = InStr(1, val, "$s")
  If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
    ni1 = ni2
  End If
  ni2 = InStr(1, val, "$i")
  If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
    ni1 = ni2
  End If
  ni2 = InStr(1, val, "$$")
  If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
    ni1 = ni2
  End If
  If ni1 = 0 Then
    Exit Sub
  Else
    i = ni1
  End If
  With R.Cells(1, 1).Characters.font
    .bold = False
    .italic = False
    .Underline = False
  End With
  bold = 0: italic = 0: underlined = 0
  
  Do While i <= n
    code = R.Cells(1, 1).Characters(i, 2).Text
    If StrComp(code, "$g") = 0 Then
      R.Cells(1, 1).Characters(i, 2).Delete
      ni = ni + 2
      If bold = 0 Then
        bold = i
      Else
        R.Cells(1, 1).Characters(bold, i - bold).font.bold = True
        bold = 0
      End If
    ElseIf StrComp(code, "$i") = 0 Then
      R.Cells(1, 1).Characters(i, 2).Delete
      ni = ni + 2
      If italic = 0 Then
        italic = i
      Else
        R.Cells(1, 1).Characters(italic, i - italic).font.italic = True
        italic = 0
      End If
    ElseIf StrComp(code, "$s") = 0 Then
      R.Cells(1, 1).Characters(i, 2).Delete
      ni = ni + 2
      If underlined = 0 Then
        underlined = i
      Else
        R.Cells(1, 1).Characters(underlined, i - underlined).font.Underline = True
        underlined = 0
      End If
    ElseIf StrComp(code, "$$") = 0 Then
      R.Cells(1, 1).Characters(i, 1).Delete
      ni = ni + 1: i = i + 1
    Else
      MsgBox "error"
    End If
    
    ni1 = InStr(i + ni, val, "$g")
    ni2 = InStr(i + ni, val, "$s")
    If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
      ni1 = ni2
    End If
    ni2 = InStr(i + ni, val, "$i")
    If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
      ni1 = ni2
    End If
    ni2 = InStr(i + ni, val, "$$")
    If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
      ni1 = ni2
    End If
    If ni1 = 0 Then
      Exit Do
    Else
      i = ni1 - ni
    End If
  Loop
  If bold > 0 Then
    R.Cells(1, 1).Characters(bold, n - bold + 1).font.bold = True
  End If
  If italic > 0 Then
    R.Cells(1, 1).Characters(italic, n - italic + 1).font.italic = True
  End If
  If underlined > 0 Then
    R.Cells(1, 1).Characters(underlined, n - underlined + 1).font.Underline = True
  End If
End Sub

'-----------------------------------------------------------------
'- RANGE_FORMATONTEMPLATE
'-----------------------------------------------------------------
'- Purpose : Formats a range according to a range template :
'-           applies the same number specifications,
'-           applies the same font and attributes,
'-           applies the same text color,
'-           applies the same alignment
'- Input   : the template, the range, the options
'- Output  : ! The sheet containing the range must be active and
'-           ! unprotected.
'-           Nothing is done if the template is not valid
'-----------------------------------------------------------------
Public Sub Range_FormatOnTemplate(template As Range, R As Range, _
                                  Optional numbers As Boolean = True, _
                                  Optional font As Boolean = True, _
                                  Optional textcolor As Boolean = True, _
                                  Optional alignment As Boolean = True)
        
    If Not template Is Nothing Then
            
        'Formats the numbers :
        If numbers Then
            R.NumberFormat = template.NumberFormat
        End If
        
        'Formats the font :
        If font Then
            R.font.name = template.font.name
            R.font.FontStyle = template.font.FontStyle
            R.font.Size = template.font.Size
            R.font.Strikethrough = template.font.Strikethrough
            R.font.Superscript = template.font.Superscript
            R.font.Subscript = template.font.Subscript
            R.font.OutlineFont = template.font.OutlineFont
            R.font.Shadow = template.font.Shadow
            R.font.Underline = template.font.Underline
        End If
        
        'Formats the text color :
        If textcolor Then
            R.Cells.font.ColorIndex = template.font.ColorIndex
        End If
        
        'Formats the alignment :
        If alignment Then
            R.HorizontalAlignment = template.HorizontalAlignment
            R.VerticalAlignment = template.VerticalAlignment
            R.WrapText = template.WrapText
            R.Orientation = template.Orientation
            R.ShrinkToFit = template.ShrinkToFit
            R.MergeCells = template.MergeCells
        End If
    End If
End Sub

'-----------------------------------------------------------------
'- RANGE_CHANGE2HYPERLINK
'-----------------------------------------------------------------
'- Purpose : Changes the range content to an hyperlink
'- Input   : the range
'- Output  : -
'-----------------------------------------------------------------
Public Sub Range_Change2Hyperlink(R As Range)
   Dim addr As String
   
   addr = LCase(CStr(R.Cells(1, 1).Value2))
   If Len(addr) > 0 Then
      If (InStr(1, addr, "@") > 0) And (InStr(1, addr, "mailto:") = 0) Then
         R.Cells(1, 1).Hyperlinks.Add R.Cells(1, 1), "mailto:" & addr
      ElseIf (InStr(1, addr, "http://") = 0) And (InStr(1, addr, "www") > 0) Then
         R.Cells(1, 1).Hyperlinks.Add R.Cells(1, 1), "http://" & addr
      Else
         R.Cells(1, 1).Hyperlinks.Add R.Cells(1, 1), addr
      End If
   End If
End Sub

'-----------------------------------------------------------------
'- RANGE_HTMLFORMAT
'-----------------------------------------------------------------
'- Purpose : formats a range containing html code.
'- Input   : the range
'- Output  : only support <b>, <i> and <u> tags.
'-----------------------------------------------------------------
Public Sub Range_HtmlFormat(R As Range)
  Dim bold As Integer, italic As Integer, underlined As Integer
  Dim n As Integer, ni As Integer, ni1 As Integer, ni2 As Integer, i As Integer
  Dim code As String, val As String
  
  If VarType(R) <> vbString Then
    Exit Sub
  End If
  
  Range_FilterHtmlTags R
  
  n = R.Characters.count: i = 1
  val = R.Value2: ni = 0
  ni1 = InStr(1, val, "<b>", vbTextCompare)
  ni2 = InStr(1, val, "<u>", vbTextCompare)
  If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
    ni1 = ni2
  End If
  ni2 = InStr(1, val, "<i>", vbTextCompare)
  If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
    ni1 = ni2
  End If
  If ni1 = 0 Then
    Exit Sub
  Else
    i = ni1
  End If
  With R.Characters.font
    .bold = False
    .italic = False
    .Underline = False
  End With
  bold = 0: italic = 0: underlined = 0
  
  Do While i <= n
    code = R.Characters(i, 3).Text
    If StrComp(code, "<b>", vbTextCompare) = 0 Then
      If bold = 0 Then
        bold = i + 3
      Else
        R.Characters(bold, i - bold).font.bold = True
        bold = 0
      End If
    ElseIf StrComp(code, "<i>", vbTextCompare) = 0 Then
      If italic = 0 Then
        italic = i + 3
      Else
        R.Characters(italic, i - italic).font.italic = True
        italic = 0
      End If
    ElseIf StrComp(code, "<u>", vbTextCompare) = 0 Then
      If underlined = 0 Then
        underlined = i + 3
      Else
        R.Characters(underlined, i - underlined).font.Underline = True
        underlined = 0
      End If
    Else
      MsgBox "error"
    End If
    
    ni1 = InStr(i + 3, val, "<b>", vbTextCompare)
    ni2 = InStr(i + 3, val, "<u>", vbTextCompare)
    If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
      ni1 = ni2
    End If
    ni2 = InStr(i + 3, val, "<i>", vbTextCompare)
    If ni1 = 0 Or ni2 > 0 And ni2 < ni1 Then
      ni1 = ni2
    End If
    If ni1 = 0 Then
      Exit Do
    Else
      i = ni1
    End If
  Loop
  If bold > 0 Then
    R.Characters(bold, n - bold + 1).font.bold = True
  End If
  If italic > 0 Then
    R.Characters(italic, n - italic + 1).font.italic = True
  End If
  If underlined > 0 Then
    R.Characters(underlined, n - underlined + 1).font.Underline = True
  End If
  'deletes html tags
  Range_FilterHtmlFormatTags R
End Sub

Private Sub Range_FilterHtmlTags(R As Range)
   R.Value2 = Replace(R.Value2, "</b>", "<b>", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "</i>", "<i>", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "</u>", "<u>", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "<br>", vbLf, Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "<br/>", vbLf, Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "<html>", "", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "</html>", "", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "<body>", "", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "</body>", "", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "</font>", "", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "&lt;", "<", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "&gt;", ">", Compare:=vbTextCompare)
   R.Value2 = Replace(R.Value2, "&amp;", "&", Compare:=vbTextCompare)
   Range_FilterHtmlStartTagWithAttributes R, "font"
End Sub

Private Sub Range_FilterHtmlStartTagWithAttributes(R As Range, tagName As String)
   Dim tag As String
   Dim i As Integer, j As Integer
   Dim s As String
   s = R.Value2
   tag = "<" & tagName
   i = InStr(1, s, tag, vbTextCompare)
   Do While i > 0
      j = InStr(i + Len(tag), s, ">", vbTextCompare)
      If j > 0 Then
         s = Left(s, i - 1) & Mid(s, j + 1)
      End If
      i = InStr(1, s, tag, vbTextCompare)
   Loop
   R.Value2 = s
End Sub

Private Sub Range_FilterHtmlFormatTags(R As Range)
   Dim i As Integer
   i = InStr(1, R.Value2, "<b>", vbTextCompare)
   Do While i > 0
      R.Characters(i, 3).Delete
      i = InStr(i, R.Value2, "<b>", vbTextCompare)
   Loop
End Sub
